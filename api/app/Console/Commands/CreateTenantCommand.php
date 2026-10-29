<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates a tenant with its only user: no self-service sign up in v1 (flows.md §0).
 */
final class CreateTenantCommand extends Command
{
    /**
     * Minimum password length.
     *
     * @var int
     */
    private const PASSWORD_MIN_LENGTH = 8;

    /**
     * {@inheritdoc}
     */
    protected $signature = 'app:create-tenant
                            {name : Nome dell\'azienda, es. "Ravizza Patrick"}
                            {email : Email per entrare nell\'app}
                            {--user-name= : Nome dell\'utente, di default quello dell\'azienda}
                            {--password= : Password; se manca viene chiesta}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Crea un\'azienda (tenant) e il suo utente';

    /**
     * @return int
     */
    public function handle(): int
    {
        $name = Str::squish((string) $this->argument('name'));
        $email = Str::lower(mb_trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?? $this->secret('Password'));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:' . self::PASSWORD_MIN_LENGTH],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $userName = Str::squish((string) $this->option('user-name'));

        DB::transaction(static function () use ($name, $userName, $email, $password): void {
            $tenant = Tenant::query()->create(['name' => $name]);

            User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => '' !== $userName ? $userName : $name,
                'email' => $email,
                'password' => $password,
            ]);
        });

        $this->info("Azienda \"{$name}\" creata. Utente: {$email}");

        return self::SUCCESS;
    }
}
