<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\RepaymentScheduleService;
use Illuminate\Console\Command;

class GenerateRepaymentSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:generate-schedules {--loan-id= : Specific loan ID to generate schedule for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate repayment schedules for existing loans';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $loanId = $this->option('loan-id');
        $service = new RepaymentScheduleService();
        
        if ($loanId) {
            // 为指定贷款生成还款计划
            $loan = Loan::find($loanId);
            if (!$loan) {
                $this->error("Loan with ID {$loanId} not found!");
                return 1;
            }
            
            $this->info("Generating repayment schedule for loan #{$loan->id}...");
            $service->saveSchedule($loan);
            $this->info("Schedule generated successfully!");
            
            return 0;
        }
        
        // 为所有没有还款计划的贷款生成还款计划
        $loans = Loan::whereDoesntHave('repaymentSchedules')->get();
        
        if ($loans->isEmpty()) {
            $this->info('All loans already have repayment schedules!');
            return 0;
        }
        
        $this->info("Found {$loans->count()} loans without repayment schedules.");
        
        $progressBar = $this->output->createProgressBar($loans->count());
        $progressBar->start();
        
        foreach ($loans as $loan) {
            try {
                $service->saveSchedule($loan);
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error generating schedule for loan #{$loan->id}: " . $e->getMessage());
            }
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info('Repayment schedules generated successfully!');
        
        return 0;
    }
}
