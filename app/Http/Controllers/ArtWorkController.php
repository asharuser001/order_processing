<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ArtWorkController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'input.quantity' => 'required|integer|min:1',
                'input.tiers' => 'required|array|min:1',
                'input.tiers.*.min' => 'required|integer|min:1',
                'input.tiers.*.price' => 'required|numeric|min:0'
            ]);

            $quantity = $request->input('input.quantity');
            $tiers = $request->input('input.tiers');

            $arrayTiers = array_filter($tiers, function ($tier) use ($quantity) { return $tier['min'] <= $quantity; });

            if (empty($arrayTiers)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => 'No valid pricing tier found'
                ]);
            }

            usort($arrayTiers, function ($a, $b) {
                if ($b['min'] !== $a['min']) {
                    return $b['min'] <=> $a['min'];
                }

                return $a['price'] <=> $b['price'];
            });

            $selectedTier = $arrayTiers[0];

            return response()->json([
                'success' => true,
                'data' => [
                    'price' => $selectedTier['price']
                ],
                'error' => null
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'data' => null,
                'error' => null
            ]);
           
        }
    }

    public function exercise1(Request $request)
    {
        try {
            $request->validate([
                'input' => 'required|array|min:1',
                'input.*.id' => 'required|integer',
                'input.*.approved' => 'required|boolean',
                'input.*.rejected' => 'required|boolean',
                'input.*.time' => 'required|integer|min:1'
            ]);

            $versions = $request->input('input');

            $selected = null;

            foreach ($versions as $item) {

                if ($item['approved'] && $item['rejected']) {
                    continue;
                }

                if ($item['approved'] && !$item['rejected']) {

                    if (
                        $selected === null ||
                        $item['time'] > $selected['time'] ||
                        ($item['time'] == $selected['time'] && $item['id'] > $selected['id'])
                    ) {
                        $selected = $item;
                    }
                }
            }

            if (!$selected) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => 'No valid version found'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $selected['id']
                ],
                'error' => null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise3(Request $request)
    {
        try {
           $request->validate([
                'input' => 'required|array|min:1',
                'input.*.id' => 'required',
                'input.*.required' => 'required|boolean',
                'input.*.done' => 'required|boolean'
            ]);

            $input = $request->input('input');

            $invalid_ids = [];


            $input = $request->input('input');

            $invalid_ids = [];

            foreach ($input as $item) {
                $required = isset($item['required']) ? (bool)$item['required'] : false;
                $done = isset($item['done']) ? (bool)$item['done'] : false;

                if ($required && !$done) {
                    $invalid_ids[] = $item['id'];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => empty($invalid_ids),
                    'invalid_items' => $invalid_ids
                ],
                'error' => null
            ], 200);

        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => null
            ], 500);
        }
    }

    public function exercise4(Request $request)
    {
        try {
            $validated = $request->validate([
                'input.order_qty' => 'required|integer|min:1',
                'input.vendors' => 'required|array|min:1',
                'input.vendors.*.id' => 'required|integer',
                'input.vendors.*.stock' => 'required|integer|min:0'
            ]);

            $orderQty = $validated['input']['order_qty'];
            $vendors = $validated['input']['vendors'];

            $totalStock = array_sum(array_column($vendors, 'stock'));

            if ($totalStock < $orderQty) {
                return response()->json([
                    'success' => false,
                    'data' => [],
                    'error' => 'Insufficient total stock from vendors'
                ], 400);
            }

            $remainingQty = $orderQty;
            $allocation = [];

            foreach ($vendors as $vendor) {

                if ($remainingQty <= 0) {
                    break;
                }

                if ($vendor['stock'] <= 0) {
                    continue;
                }

                $allocated = min($vendor['stock'], $remainingQty);

                $allocation[] = [
                    'vendor_id' => $vendor['id'],
                    'allocated' => $allocated
                ];

                $remainingQty -= $allocated;
            }

            if ($remainingQty > 0) {
                return response()->json([
                    'success' => false,
                    'data' => [],
                    'error' => 'Allocation failed'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $allocation,
                'error' => null
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'data' => [],
                'error' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise5(Request $request)
    {
        try {

            $validated = $request->validate([
                'input.price' => 'required|numeric|min:0',
                'input.discounts' => 'required|array|min:1',
                'input.discounts.*.type' => 'required|in:percentage,flat',
                'input.discounts.*.value' => 'required|numeric|min:0',
            ], [
                'input.price.min' => 'Price cannot be negative.',
                'input.price.numeric' => 'Price must be a valid number.',
            ]);

            $price = (float) $validated['input']['price'];
            $discounts = $validated['input']['discounts'];

            if ($price < 0) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'message' => 'Price cannot be negative.',
                    ]
                ], 422);
            }

            $bestPrice = $price;

            foreach ($discounts as $discount) {
                $type = strtolower($discount['type']);
                $value = (float) $discount['value'];

                $finalPrice = $price;

                if ($type === 'percentage') {
                    $finalPrice = $price * (1 - $value / 100);
                } elseif ($type === 'flat') {
                    $finalPrice = $price - $value;
                }

                if ($finalPrice < $bestPrice) {
                    $bestPrice = $finalPrice;
                }
            }

            $bestPrice = max(0, round($bestPrice, 2));

            return response()->json([
                'success' => true,
                'data' => [
                    'final_price' => $bestPrice,
                ],
                'error' => null
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'message' => 'Validation failed',
                    'details' => $e->errors()
                ]
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'message' => 'Something went wrong while calculating discount.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ]
            ], 500);
        }
        
    }

    // public function exercise6(Request $request)
    // {
    //     try{

    //         $validate = $request->validate([
    //             'input' => 'required|array|min:1',
    //             'input.steps' => 'required|array|min:1',
    //             'input.steps.*.id' => 'required|string',
    //             'input.steps.*.depends_on' => 'nullable|string'
    //         ]);

    //         $steps = $validate['input']['steps'];
    //         $stepMap = [];

    //         foreach ($steps as $step) {
    //             $stepMap[$step['id']] = $step['depends_on'];
    //         }
    //         $visited = [];
    //         $result = [];
    //         foreach ($stepMap as $id => $dependsOn) {
    //             if (!isset($visited[$id])) {
    //                 $stack = [];
    //                 $current = $id;

    //                 while ($current !== null) {
    //                     if (isset($visited[$current])) {
    //                         break;
    //                     }
    //                     if (in_array($current, $stack)) {
    //                         return response()->json([
    //                             'success' => false,
    //                             'data' => null,
    //                             'error' => 'Circular dependency detected'
    //                         ], 400);
    //                     }
    //                     $stack[] = $current;
    //                     $current = $stepMap[$current] ?? null;
    //                 }

    //                 foreach (array_reverse($stack) as $stepId) {
    //                     if (!isset($visited[$stepId])) {
    //                         $result[] = $stepId;
    //                         $visited[$stepId] = true;
    //                     }
    //                 }

    //                 return response()->json([
    //                     'success' => true,
    //                     'data' => json_encode(['valid' => $result, JSON_PRETTY_PRINT]),
    //                     'error' => null
    //                 ], 200);
    //             }
    //         }
    //     }
    //     catch(\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'data' => null,
    //             'error' => $e->getMessage()
    //         ], 422);
    //     }
        
    // }

    public function exercise6(Request $request)
    {
        try {
        $request->validate([
            'input' => 'required|array|min:1',
            'input.steps' => 'required|array|min:1',
            'input.steps.*.id' => 'required|string',
            'input.steps.*.depends_on' => 'nullable|string',
        ], [
            'input.steps.*.id.required' => 'Each step must have an id.',
            'input.steps.*.id.string' => 'Each step id must be a string.',
            'input.steps.*.depends_on.string' => 'Each step dependency must be a string or null.',
        ]
        );

        $steps = $request->input('input.steps');

        $result = $this->validateApprovalFlow($steps);

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $result['valid']
            ],
            'error' => $result['error']
        ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    private function validateApprovalFlow(array $steps): array
    {
        $stepMap = [];
        $graph = [];
        $in = [];

        foreach ($steps as $step) {
            $id = $step['id'];
            $dependsOn = $step['depends_on'] ?? null;

            $stepMap[$id] = true;
            $graph[$id] = $dependsOn;

            if (!isset($in[$id])) {
                $in[$id] = 0;
            }
        }

        foreach ($graph as $id => $dependsOn) {
            if ($dependsOn !== null) {
                if (!isset($stepMap[$dependsOn])) {
                    return [
                        'valid' => false,
                        'error' => "Missing dependency: Step '{$id}' depends on non-existent step '{$dependsOn}'"
                    ];
                }
                $in[$dependsOn] = ($in[$dependsOn] ?? 0) + 1;
            }
        }

        $queue = [];
        foreach ($in as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $processed = 0;
        while (!empty($queue)) {
            $current = array_shift($queue);
            $processed++;

            $parent = $graph[$current] ?? null;
            if ($parent !== null) {
                $in[$parent]--;
                if ($in[$parent] === 0) {
                    $queue[] = $parent;
                }
            }
        }

        if ($processed !== count($steps)) {
            return [
                'valid' => false,
                'error' => 'Disconnected'
            ];
        }

        return [
            'valid' => true,
            'error' => null
        ];
    }

    public function exercise7(Request $request)
    {
        try {
            $request->validate([
                'input.stock'     => 'required|integer|min:0',
                'input.requests'  => 'required|array|min:1',
                'input.requests.*' => 'integer',
            ], [
                'input.stock.required'    => 'Stock quantity is required.',
                'input.stock.integer'     => 'Stock must be a valid integer.',
                'input.stock.min'         => 'Stock cannot be negative.',
                'input.requests.required' => 'Requests array is required.',
                'input.requests.array'    => 'Requests must be an array.',
                'input.requests.min'      => 'At least one reservation request is required.',
            ]);

            $input = $request->input('input');
            $stock = (int) $input['stock'];
            $requests = $input['requests'];

            $remainingStock = $stock;
            $results = [];

            foreach ($requests as $req) {
                $amount = (int) $req;

                if ($amount > 0 && $amount <= $remainingStock) {
                    $results[] = true;
                    $remainingStock -= $amount;
                } else {
                    $results[] = false;
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $results,
                'error'   => null
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Validation failed',
                'messages' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise8(Request $request)
    {
        try
        {
            $validated = $request->validate([
                'input.ordered' => 'required|integer|min:1',
                'input.shipped' => 'required|array|min:1',
                'input.shipped.*' => 'integer|min:0'
            ], [
                'input.ordered.required' => 'Ordered quantity is required.',
                'input.ordered.integer' => 'Ordered quantity must be an integer.',
                'input.ordered.min' => 'Ordered quantity must be at least 1.',
                'input.shipped.required' => 'Shipped array is required.',
                'input.shipped.array' => 'Shipped must be an array.',
                'input.shipped.min' => 'At least one shipped entry is required.',
                'input.shipped.*.integer' => 'Each shipped quantity must be an integer.',
                'input.shipped.*.min' => 'Shipped quantities cannot be negative.'
            ]);

            $ordered = $validated['input']['ordered'];
            $shipped = $validated['input']['shipped'];

            $shippedArray = array_sum($shipped);
            $totalShipped = $ordered - $shippedArray;
            $remaining = max(0, $totalShipped);

            return response()->json([
                'success' => true,
                'data'    => ['remaining' => $remaining],
                'error'   => null
            ], 200);


        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Validation failed',
                'messages' => $e->errors()
            ], 422);

        }
         catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise9(Request $request)
    {
        try {
            $validated = $request->validate([
                'input' => 'required|array|min:1',
                'input.*.id' => 'required|string',
                'input.*.time' => 'required|integer|min:1'
            ], [
                'input.required' => 'Input array is required.',
                'input.array' => 'Input must be an array.',
                'input.min' => 'At least one entry is required in the input array.',
                'input.*.id.required' => 'Each entry must have an id.',
                'input.*.id.string' => 'Each id must be a string.',
                'input.*.time.required' => 'Each entry must have a time value.',
                'input.*.time.integer' => 'Time value must be an integer.',
                'input.*.time.min' => 'Time value must be at least 1.'
            ]);

            $input = $validated['input'];

            $ids = collect($input)->pluck('id')->unique()->values();

            return response()->json([
                'success' => true,
                'data'    => $ids,
                'error'   => null
            ], 200);

        }
        catch (\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => $e->getMessage()
            ], 500);
        }
        
    }

    public function exercise10(Request $request)
    {
        try {
            $validated = $request->validate([
                'input.created_at' => 'required|date',
                'input.valid_days' => 'required|integer|min:1',
                'input.current_date' => 'required|date'
            ], [
                'input.created_at.required' => 'Creation date is required.',
                'input.created_at.date' => 'Creation date must be a valid date.',
                'input.valid_days.required' => 'Valid days is required.',
                'input.valid_days.integer' => 'Valid days must be an integer.',
                'input.valid_days.min' => 'Valid days must be at least 1.',
                'input.current_date.required' => 'Current date is required.',
                'input.current_date.date' => 'Current date must be a valid date.'
            ]);

            $createdAt = new \DateTime($validated['input']['created_at']);
            $validDays = (int) $validated['input']['valid_days'];
            $currentDate = new \DateTime($validated['input']['current_date']);

            $expiryDate = (clone $createdAt)->modify("+{$validDays} days");

            $isExpired = $currentDate > $expiryDate;

            return response()->json([
                'success' => true,
                'data'    => ['valid' => !$isExpired],
                'error'   => null
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Validation failed',
                'messages' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => $e->getMessage()
            ], 500);
        }
        
    }

    public function exercise11(Request $request)
    {
        try{
            $validated = $request->validate([
                    'input' => 'required|array',
                    'input.customer' => 'required|array',
                    'input.customer.tags' => 'required|array',
                    'input.customer.tags.*' => 'string',
                    'input.products' => 'required|array',
                    'input.products.*.id' => 'required|integer',
                    'input.products.*.allow' => 'nullable|array',
                    'input.products.*.allow.*' => 'string',
                    'input.products.*.block' => 'nullable|array',
                    'input.products.*.block.*' => 'string',
                ]);

                $customerTags = collect($validated['input']['customer']['tags']);
                $products = $validated['input']['products'];

                $visibleIds = [];

                foreach ($products as $product) {
                    $allowTags = collect($product['allow']);
                    $blockTags = collect($product['block']);

                    $isBlocked = $blockTags->intersect($customerTags)->isNotEmpty();
                    if ($isBlocked) {
                        continue;
                    }
                    $hasAccess = true;
                    if ($allowTags->isNotEmpty()) {
                        $hasAccess = $allowTags->intersect($customerTags)->isNotEmpty();
                    }

                    if ($hasAccess) {
                        $visibleIds[] = $product['id'];
                    }
                }

                return response()->json([
                    "success" => true,
                    'data' => $visibleIds,
                    'error' => null

                ], 200);
        }
        catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'Validation failed',
                'messages' => $e->errors()
            ], 422);

        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'An unexpected error occurred.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise12(Request $request)
    {
        try {
            $validated = $request->validate([
                'input' => 'required|array',
                'input.items' => 'required|array|min:1',
                'input.items.*.id' => 'required|integer|min:1',
                'input.items.*.price' => 'required|numeric|min:0',
                'input.bundle_price' => 'required|numeric|min:0',
                'input.apply_bundle' => 'required|boolean',
            ],
            [
                'input.items.*.id.required' => 'Each item must have an id.',
                'input.items.*.id.integer' => 'Each item id must be an integer.',
                'input.items.*.id.min' => 'Each item id must be at least 1.',
                'input.items.*.price.required' => 'Each item must have a price.',
                'input.items.*.price.numeric' => 'Each item price must be a valid number.',
                'input.items.*.price.min' => 'Each item price cannot be negative.',
                'input.bundle_price.required' => 'Bundle price is required.',
                'input.bundle_price.numeric' => 'Bundle price must be a valid number.',
                'input.bundle_price.min' => 'Bundle price cannot be negative.',
                'input.apply_bundle.required' => 'Apply bundle flag is required.',
                'input.apply_bundle.boolean' => 'Apply bundle flag must be a boolean value.'
            ]);

            $data = $validated['input'];
            $items = $data['items'];
            $bundlePrice = $data['bundle_price'];
            $applyBundle = $data['apply_bundle'];

            $grandTotal = 0;
            foreach ($items as $item) {
                $grandTotal += $item['price'];
            }

            $finalPrice = $grandTotal;

            if ($applyBundle && $bundlePrice < $grandTotal) {
                $finalPrice = $bundlePrice;
            }

            return response()->json([
                'success' => true,
                'data' => ['price' => $finalPrice],
                'error' => null
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise13(Request $request)
    {
        try {
            $validated = $request->validate([
                'input'              => 'required|array',
                'input.guest'        => 'required|array',
                'input.user'         => 'required|array',
                'input.guest.*.id'   => 'required|integer|min:1',
                'input.guest.*.qty'  => 'required|integer|min:0',
                'input.user.*.id'    => 'required|integer|min:1',
                'input.user.*.qty'   => 'required|integer|min:0',
            ]);

            $guestCart = $validated['input']['guest'];
            $userCart  = $validated['input']['user'];

            $cartArray = [];

            foreach ($guestCart as $item) {
                $cartArray[$item['id']] = [
                    'id'  => (int)$item['id'],
                    'qty' => (int)$item['qty']
                ];
            }

            foreach ($userCart as $item) {
                $id = $item['id'];
                if (isset($cartArray[$id])) {
                    $cartArray[$id]['qty'] += (int)$item['qty'];
                } else {
                    $cartArray[$id] = [
                        'id'  => (int)$id,
                        'qty' => (int)$item['qty']
                    ];
                }
            }

            $mergedCart = array_values($cartArray);
            usort($mergedCart, fn($a, $b) => $a['id'] <=> $b['id']);

            return response()->json([
                'success'    => true,
                'data' => ['merge' => $mergedCart]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 400);
        }
    }

    public function exercise14(Request $request)
    {
        try {                                
            $validated = $request->validate([
                'input.nums'    => 'required|array|min:2',
                'input.nums.*'  => 'required|integer',  
                'input.target'  => 'required|integer',
            ]);   
            $nums   = $validated['input']['nums'];
            $target = $validated['input']['target'];

            $total = count($nums);


            for ($i = 0; $i < $total; $i++) {
                for ($j = $i + 1; $j < $total; $j++) {
                    
                    if ($nums[$i] + $nums[$j] === $target) {
                        return response()->json([
                            'success' => true,
                            'data'    =>  [$i, $j],
                            'error'   => null
                        ], 200);
                    }         
                }
            }

            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => 'No two numbers add up to the target'
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function exercise15(Request $request)
    {
        try {
            $validated = $request->validate([
                'input' => 'required|array',
                'input.order' => 'required|array',
                'input.order.weight' => 'required|numeric|min:0',
                'input.order.country' => 'required|string',
                'input.rules' => 'required|array|min:1',
                'input.rules.*.id' => 'required|integer',
                'input.rules.*.method' => 'required|string',
                'input.rules.*.priority' => 'required|integer|min:1',
                'input.rules.*.max_weight' => 'nullable|numeric|min:0',
                'input.rules.*.country' => 'nullable|string',
            ]);

            $order = $validated['input']['order'];
            $rules = $validated['input']['rules'];

            $matchedRules = [];

            foreach ($rules as $rule) {

                $isMatched = true;

                if (isset($rule['max_weight'])) {
                    if ($order['weight'] > $rule['max_weight']) {
                        $isMatched = false;
                    }
                }

                if (isset($rule['country'])) {
                    if (
                        strtolower($order['country']) !== strtolower($rule['country'])
                    ) {
                        $isMatched = false;
                    }
                }

                if ($isMatched) {
                    $matchedRules[] = $rule;
                }
            }

            if (empty($matchedRules)) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => 'No shipping rule matched'
                ], 404);
            }

            // usort($matchedRules, function ($a, $b) {
            //     return $b['priority'] <=> $a['priority'];
            // });

            $selectedRule = $matchedRules[0];

            return response()->json([
                'success' => true,
                'data' => $selectedRule['method'],
                'error' => null
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'data'    => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise16(Request $request)
    {
        try {
            $validated = $request->validate([
                'input' => 'required|array',
                'input.order' => 'required|array',
                'input.order.amount' => 'required|numeric|min:0',
                'input.order.country' => 'required|string',
                'input.order.previous_orders' => 'required|integer|min:0',
                'input.rules' => 'required|array',
                'input.rules.max_amount' => 'required|numeric|min:0',
                'input.rules.blocked_countries' => 'required|array',
                'input.rules.blocked_countries.*' => 'string'
            ]);

            $order = $validated['input']['order'];
            $rules = $validated['input']['rules'];

            $isFraudulent = false;
            $reasons = [];

            if ($order['amount'] > $rules['max_amount']) {
                $isFraudulent = true;
                $reasons[] = 'Amount exceeds maximum limit';
            }

            if (in_array(strtolower($order['country']), array_map('strtolower', $rules['blocked_countries']))) {
                $isFraudulent = true;
                $reasons[] = 'Country is blocked';
            }

            if($isFraudulent){
                return response()->json([
                    'success' => true,
                    'data'    => ['flagged' => $isFraudulent],
                    'error'   => null
                ], 200);
            }
            else {
                return response()->json([
                    'success' => true,
                    'data'    => ['flagged' => $isFraudulent],
                    'error'   => null
                ], 200);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage()
            ], 500);
        }

    }

    public function exercise17(Request $request)
    {
        try {
            $request->validate([
                'input' => 'required|array',
                'input.prices' => 'required|array|min:1',
                'input.prices.*' => 'required|array|min:1',
                'input.prices.*.*' => 'required|integer',
                'input.adjustment_value' => 'required|integer|min:1'
            ]);

            $arr = $request->input('input.prices');
            $adj = $request->input('input.adjustment_value');

            $p_arr = [];

            foreach ($arr as $a1) {
                foreach ($a1 as $p1) {
                    $p_arr[] = $p1;
                }
            }

            $b1 = $p_arr[0];
            foreach ($p_arr as $p2) {
                if (($p2 - $b1) % $adj != 0) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'minimum_operations' => -1
                        ]
                    ], 200);
                }
            }

            sort($p_arr);

            $count = count($p_arr);

            $m = $p_arr[intval($count / 2)];

            $output = 0;

            foreach ($p_arr as $p2) {

                $output += abs($p2 - $m) / $adj;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'minimum_operations' => (int)$output
                ],
                'error' => null
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise18(Request $request)
    {
        try{
            $validated = $request->validate([
                'input' => 'required|array',
                'input.shopify' => 'required|array|min:1',
                'input.shopify.price' => 'required|numeric|min:0',
                'input.shopify.updated_at' => 'required|integer|min:0',
                'input.internal' => 'required|array|min:1',
                'input.internal.price' => 'required|numeric|min:0',
                'input.internal.updated_at' => 'required|integer|min:0'
            ]);

            $shopify = $validated['input']['shopify'];
            $internal = $validated['input']['internal'];
            Log::info('Shopify Data: ' . json_encode($shopify));
            Log::info('Internal Data: ' . json_encode($internal));
            if ($internal['updated_at'] > $shopify['updated_at']) {
                Log::info('Using internal data: ' . $internal['updated_at'] . ' > ' . $shopify['updated_at']);
                $price = $internal['price'];
                $source = 'internal';
            } else {
                Log::info('Using shopify data: ' . $shopify['updated_at'] . ' >= ' . $internal['updated_at']);
                $price = $shopify['price'];
                $source = 'shopify';
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'result' => $price
                ],
                'error' => null
            ], 200);
            
        }
        catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exercise19(Request $request)
    {
        try {
            $validated = $request->validate([
                'input' => 'required|array',
                'input.options' => 'required|array|min:1',
                'input.options.*.name' => 'required|string',
                'input.options.*.values' => 'required|integer|min:1',
                'input.limit' => 'required|integer|min:1'
            ]);

            $variants = $validated['input']['options'];
            $limit = $validated['input']['limit'];


            $arr = array_column($variants, 'values');

            Log::info('Variant values: ' . json_encode($arr));

            $totalCombinations = array_product($arr);
            Log::info('Total combinations: ' . $totalCombinations);

            if($totalCombinations > $limit){
                $limitExceeded = true;
            } else {
                $limitExceeded = false;
            }
            Log::info('Limit exceeded: ' . ($limitExceeded));

            return response()->json([
                'success' => true,
                'data' => [
                    'total_combinations' => $totalCombinations,
                    'exceeded' => $limitExceeded
                ],
                'error' => null
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'error'   => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }   
        
    }

    public function exercise20(Request $request)
    {
        try{

            $validated = $request->validate([
                'input' => 'required|array',
                'input.transitions' => 'required|array|min:1',
                'input.transitions.*' => 'required|string|in:created,paid,processing,shipped,delivered'
            ]);

            $transitions = $validated['input']['transitions'];

            $arr = ['created', 'paid', 'processing', 'shipped', 'delivered'];
            $Index = 0;

            foreach ($transitions as $value) {
                if ($value !== $arr[$Index]) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'valid' => false
                        ],
                        'error' => null
                    ], 200);
                }
                $Index++;
                if ($Index >= count($arr)) {
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => true
                ],
                'error' => null
            ], 200);

        }
        catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'data'    => null,
            'error'   => $e->getMessage(),
        ], 422);
        
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
