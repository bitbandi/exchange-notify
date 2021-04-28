<?php

test('query command', function () {
    $this->artisan('query')
         ->expectsOutput('There are no registered exchanges.')
         ->assertExitCode(0);
});
