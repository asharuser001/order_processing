<?php

namespace App\Console\Commands;

use App\Models\OrderTimelineEvent;
use App\Models\ShopifyOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CleanDuplicateOrdersCommand
 *
 * Merges duplicate ShopifyOrder rows caused by a GID vs numeric ID mismatch.
 *
 * Root cause: GraphQL sync stored "gid://shopify/Order/7509625471258" while
 * webhooks stored the plain numeric "7509625471258". Both were valid strings
 * that satisfied the unique(user_id, shopify_order_id) constraint separately,
 * producing two rows for the same Shopify order.
 *
 * What this command does:
 *  1. Find all rows where shopify_order_id starts with "gid://shopify/Order/"
 *  2. For each GID row, derive the numeric ID.
 *  3. If a numeric row already exists for that user:
 *       - Re-link timeline events (skip true duplicates by event_type+source)
 *       - Delete the GID row.
 *  4. If no numeric row exists:
 *       - Update the GID row's shopify_order_id to the numeric value in place.
 *       - Update any timeline events that still reference the old GID string.
 *
 * Run with --dry-run to preview changes without touching the database.
 */
class CleanDuplicateOrdersCommand extends Command
{
    protected $signature   = 'orders:clean-duplicates
                                {--dry-run : Preview changes without modifying any data}';
    protected $description = 'Merge duplicate ShopifyOrder rows caused by GID vs numeric order ID mismatch';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun
            ? '=== DRY RUN — no data will be modified ==='
            : '=== Cleaning duplicate ShopifyOrder rows ===');
        $this->newLine();

        // ── 0. Report current duplicates (by order_name) ───────────────
        $this->reportDuplicates();

        // ── 1. Find all GID-format rows ────────────────────────────────
        $gidOrders = ShopifyOrder::where('shopify_order_id', 'like', 'gid://shopify/Order/%')
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        if ($gidOrders->isEmpty()) {
            $this->info('No GID-format rows found. Nothing to clean.');
            return self::SUCCESS;
        }

        $this->info("Found {$gidOrders->count()} GID-format order row(s) to process.");
        $this->newLine();

        $merged    = 0;
        $converted = 0;

        foreach ($gidOrders as $gidOrder) {
            $numericId = str_replace('gid://shopify/Order/', '', $gidOrder->shopify_order_id);

            $this->line("GID row #{$gidOrder->id} | user {$gidOrder->user_id} | {$gidOrder->order_name} → numeric {$numericId}");

            // Check if a numeric row already exists for this shop
            $numericOrder = ShopifyOrder::where('user_id', $gidOrder->user_id)
                ->where('shopify_order_id', $numericId)
                ->first();

            if ($numericOrder) {
                $this->line("  Numeric row #{$numericOrder->id} already exists — merging timeline events then deleting GID row.");
                $this->mergeTimelineEvents($gidOrder, $numericOrder, $numericId, $dryRun);

                if (! $dryRun) {
                    $gidOrder->delete();
                    Log::info('[CleanDuplicateOrders] Deleted GID row after merge', [
                        'gid_row_id'      => $gidOrder->id,
                        'numeric_row_id'  => $numericOrder->id,
                        'numeric_order_id' => $numericId,
                        'user_id'         => $gidOrder->user_id,
                    ]);
                }
                $merged++;

            } else {
                // No numeric row — safe to just update shopify_order_id in place
                $this->line("  No numeric row found — converting GID row to numeric ID in place.");

                if (! $dryRun) {
                    // Update any timeline events that still carry the old GID string
                    OrderTimelineEvent::where('user_id', $gidOrder->user_id)
                        ->where('shopify_order_id', $gidOrder->shopify_order_id)
                        ->update(['shopify_order_id' => $numericId]);

                    $gidOrder->update(['shopify_order_id' => $numericId]);

                    Log::info('[CleanDuplicateOrders] Converted GID row to numeric', [
                        'row_id'          => $gidOrder->id,
                        'old_id'          => $gidOrder->getOriginal('shopify_order_id'),
                        'new_id'          => $numericId,
                        'user_id'         => $gidOrder->user_id,
                    ]);
                }
                $converted++;
            }
        }

        $this->newLine();
        $this->info("Done.  Merged (GID row deleted): {$merged}  |  Converted in place: {$converted}");

        if ($dryRun) {
            $this->warn('This was a DRY RUN. Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            $this->info('Post-cleanup duplicate report:');
            $this->reportDuplicates();
        }

        return self::SUCCESS;
    }

    // ── Private helpers ──────────────────────────────────────────────────

    /**
     * Move timeline events from the GID row to the numeric row.
     * Skips any event that already exists on the numeric row (same type+source).
     */
    private function mergeTimelineEvents(
        ShopifyOrder $gidOrder,
        ShopifyOrder $numericOrder,
        string       $numericId,
        bool         $dryRun
    ): void {
        $events = OrderTimelineEvent::where('shopify_order_id_local', $gidOrder->id)->get();

        // Also pick up any events that still carry the GID string but lost the FK
        $orphans = OrderTimelineEvent::where('user_id', $gidOrder->user_id)
            ->where('shopify_order_id', $gidOrder->shopify_order_id)
            ->get();

        $all = $events->merge($orphans)->unique('id');

        if ($all->isEmpty()) {
            $this->line("  No timeline events to move.");
            return;
        }

        $this->line("  Moving {$all->count()} timeline event(s)...");

        foreach ($all as $event) {
            $alreadyExists = OrderTimelineEvent::where('user_id', $event->user_id)
                ->where('shopify_order_id', $numericId)
                ->where('event_type', $event->event_type)
                ->where('source', $event->source)
                ->exists();

            if ($alreadyExists) {
                $this->line("    - [{$event->event_type}/{$event->source}] already on numeric row → deleting duplicate.");
                if (! $dryRun) {
                    $event->delete();
                }
            } else {
                $this->line("    - [{$event->event_type}/{$event->source}] moved to numeric row #{$numericOrder->id}.");
                if (! $dryRun) {
                    $event->update([
                        'shopify_order_id_local' => $numericOrder->id,
                        'shopify_order_id'       => $numericId,
                    ]);
                }
            }
        }
    }

    /**
     * Print a table of order_name values that appear more than once per user.
     */
    private function reportDuplicates(): void
    {
        $duplicates = ShopifyOrder::selectRaw('user_id, order_name, COUNT(*) as total, GROUP_CONCAT(shopify_order_id ORDER BY id SEPARATOR " | ") as ids')
            ->groupBy('user_id', 'order_name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->line('  No duplicate order_name rows found.');
            return;
        }

        $this->warn("  Duplicate order rows found ({$duplicates->count()} order name(s) with multiple rows):");
        $this->table(
            ['user_id', 'order_name', 'count', 'shopify_order_id values'],
            $duplicates->map(fn ($r) => [$r->user_id, $r->order_name, $r->total, $r->ids])
        );
    }
}
