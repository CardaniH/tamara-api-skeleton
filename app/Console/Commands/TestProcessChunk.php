<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestProcessChunk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-process-chunk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
{
    // Test con datos válidos
    $validDocuments = [
        ['id' => '1', 'name' => 'test1.pdf'],
        ['id' => '2', 'name' => 'test2.docx']
    ];
    
    // Test con datos inválidos
    $invalidInputs = [
        null,
        'string',
        123,
        [],
    ];
    
    foreach ($invalidInputs as $index => $input) {
        try {
            $job = new ProcessSharePointChunk("test_chunk_{$index}", $input, $index);
            $this->info("✅ Job creado con input: " . gettype($input));
        } catch (\Exception $e) {
            $this->error("❌ Error con input " . gettype($input) . ": " . $e->getMessage());
        }
    }
}
}
