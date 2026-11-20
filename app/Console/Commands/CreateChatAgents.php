<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateChatAgents extends Command
{
    protected $signature = 'chat:agents
        {--email=* : Agent as Name=email@domain. Names: Victor, Sbu, Charity, Lydia}
        {--reset : Replace the password of an agent who already has an account}';

    protected $description = 'Create live-chat agent logins. Passwords are random and printed once; they are not stored in the repo.';

    public function handle(): int
    {
        $role = Role::query()->where('slug', 'chat_agent')->first();
        if (! $role) {
            $this->error('The chat_agent role is missing. Deploy so migrations run, then try again.');

            return self::FAILURE;
        }

        $pairs = $this->option('email');
        if ($pairs === []) {
            $this->line('Pass one --email for each person, for example:');
            $this->line('  php artisan chat:agents --email="Victor=victor@urbanfocus.co.za" --email="Sbu=sbu@urbanfocus.co.za" --email="Charity=charity@urbanfocus.co.za" --email="Lydia=lydia@urbanfocus.co.za"');
            $this->line('Add --reset only when you want a new password for an existing account.');

            return self::SUCCESS;
        }

        $allowed = ['victor', 'sbu', 'charity', 'lydia'];
        $created = 0;

        foreach ($pairs as $pair) {
            if (! is_string($pair) || ! str_contains($pair, '=')) {
                $this->error('Use Name=email@domain.');

                return self::FAILURE;
            }

            [$name, $email] = array_map('trim', explode('=', $pair, 2));
            if (! in_array(strtolower($name), $allowed, true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Each agent must be Victor, Sbu, Charity or Lydia, with a real email.');

                return self::FAILURE;
            }

            $user = User::query()->where('email', $email)->first();
            $password = Str::password(20);
            if (! $user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'is_admin' => true,
                    'is_active' => true,
                ]);
                $user->syncRoles(['chat_agent']);
                $this->line($name.' '.$email);
                $this->line('Password: '.$password);
                $created++;

                continue;
            }

            if (! $this->option('reset')) {
                $user->syncRoles(array_values(array_unique(array_merge(
                    $user->roles()->pluck('slug')->all(),
                    ['chat_agent']
                ))));
                $this->line($name.' already has an account at '.$email.'. Password was left unchanged. Use --reset to set a new one.');

                continue;
            }

            $user->forceFill([
                'name' => $name,
                'password' => $password,
                'is_admin' => true,
                'is_active' => true,
            ])->save();
            $user->syncRoles(['chat_agent']);
            $this->line($name.' '.$email);
            $this->line('Password: '.$password);
            $created++;
        }

        if ($created === 0) {
            $this->line('No new passwords were created.');
        }

        return self::SUCCESS;
    }
}
