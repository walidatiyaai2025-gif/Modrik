<?php
namespace App\Services\Updates;
interface WebRestartAdapter { public function restart(string $releasePath): RestartResult; }
