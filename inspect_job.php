\ = DB::table('failed_jobs')
    ->where('payload', 'like', '%App\\\\\\\\Jobs\\\\\\\\RetryFailedWebhookJob%')
    ->latest('failed_at')
    ->first();

if (\) {
    \ = explode(\"\n\", \->exception);
    echo \"Exception (First 15 lines):\n\";
    echo implode(\"\n\", array_slice(\, 0, 15)) . \"\n\";
    
    echo \"\nPayload (First 300 chars):\n\";
    echo substr(\->payload, 0, 300) . \"\n\";
} else {
    echo \"No matching failed job found for App\Jobs\RetryFailedWebhookJob.\n\";
}
