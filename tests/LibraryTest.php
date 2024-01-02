<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateKLF200Gateway(): void
    {
        $this->validateModule(__DIR__ . '/../KLF200Gateway');
    }

    public function testValidateKLF200Configurator(): void
    {
        $this->validateModule(__DIR__ . '/../KLF200Configurator');
    }
    public function testValidateKLF200Node(): void
    {
        $this->validateModule(__DIR__ . '/../KLF200Node');
    }
}
