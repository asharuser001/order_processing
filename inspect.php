<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function inspectFailedJobs($jobs) {
    echo "--- FAILED JOBS ---\n";
    foreach ($jobs as $jobClass) {
        $job = Illuminate\Support\Facades\DB::table("failed_jobs")
            ->where("payload", "like", "%" . str_replace("\\", "\\\\", $jobClass) . "%")
            ->orderBy("failed_at", "desc")
            ->first();

        if ($job) {
            echo "ID: {$job->id} | Queue: {$job->queue} | Job: {$jobClass}\n";
            $exceptionLines = explode("\n", $job->exception);
            echo "Exception (Top 20 lines):\n" . implode("\n", array_slice($exceptionLines, 0, 20)) . "\n";
            
            $payload = json_decode($job->payload, true);
            $command = $payload["data"]["command"] ?? "N/A";
            echo "Payload Summary (command start): " . substr($command, 0, 300) . "...\n";
            echo "---------------------------------\n";
        } else {
            echo "No failed jobs found for: {$jobClass}\n";
        }
    }
}

function inspectWebhookEvents() {
    echo "--- LATEST FAILED WEBHOOK EVENTS ---\n";
    try {
        $events = Illuminate\Support\Facades\DB::table("webhook_events")
            ->orderBy("id", "desc")
            ->limit(10)
            ->get();

        foreach ($events as $event) {
            echo "ID: {$event->id} | Topic: " . ($event->topic ?? "N/A") . " | Status: " . ($event->status ?? "N/A") . " | Attempts: " . ($event->attempts ?? "0") . " | Processed: " . ($event->processed_at ?? "N/A") . "\n";
            echo "Error: " . substr($event->error_message ?? "None", 0, 100) . "\n";
            echo "---------------------------------\n";
        }
    } catch (\Exception $e) {
        echo "Error querying webhook_events: " . $e->getMessage() . "\n";
    }
}

inspectFailedJobs(["App\\Jobs\\RetryFailedWebhookJob", "App\\Jobs\\SyncShopifyOrdersJob"]);
inspectWebhookEvents();
