<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * Demonstrates the audit trail's redaction of sensitive values.
 *
 * The AuditLogger strips sensitive keys from every payload before it is stored,
 * but no ordinary user action happens to log one — an application carries no
 * password or identity number in its changed attributes. This command writes a
 * payload that does, through the same AuditLogger every other audit write in the
 * system uses, so the control can be seen working in the Audit Trail screen
 * rather than only inferred from the code.
 *
 *     php artisan aidbridge:demo-redaction
 *
 * The resulting entry appears as `demo.redaction_check`, with nric, password and
 * the nested token all replaced by [redacted], while an ordinary key survives.
 */
class DemoRedactionCommand extends Command
{
    protected $signature = 'aidbridge:demo-redaction';

    protected $description = 'Write a demonstration audit entry showing sensitive values being redacted';

    public function handle(AuditLogger $auditLogger): int
    {
        $auditLogger->log('demo.redaction_check', null, [
            'nric' => '900101145566',
            'password' => 'hunter2',
            'nested' => ['token' => 'abc123'],
            'note' => 'this stays visible',
        ]);

        $this->newLine();
        $this->info('Demonstration audit entry written.');
        $this->line('Open AidBridge -> Audit Trail and look for: demo.redaction_check');
        $this->line('Click "View payload" to see the redacted values.');
        $this->newLine();

        return self::SUCCESS;
    }
}
