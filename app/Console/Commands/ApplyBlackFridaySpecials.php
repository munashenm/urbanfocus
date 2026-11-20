<?php

namespace App\Console\Commands;

use App\Services\BlackFridaySpecialService;
use Illuminate\Console\Command;

class ApplyBlackFridaySpecials extends Command
{
    protected $signature = 'black-friday:apply';

    protected $description = 'Apply configured Black Friday prices without raising a regular price or replacing a sale that is already set';

    public function handle(BlackFridaySpecialService $specials): int
    {
        $result = $specials->apply();
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
