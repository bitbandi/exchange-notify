<?php

test('query command', function () {
    $this->artisan('query')
         ->expectsOutput('Simplicity is the ultimate sophistication.')
         ->assertExitCode(0);
});
