<?php
/**
 * Unit tests for ConfigHelper
 *
 * Focuses on environment variable resolution: defaults must be applied when a
 * variable is missing or blank, and boolean-like strings must be converted.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\ConfigHelper;
use PHPUnit\Framework\TestCase;

class ConfigHelperTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $touchedEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->touchedEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
                continue;
            }
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
        $this->touchedEnv = [];
    }

    private function setEnv(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->touchedEnv)) {
            $previous = getenv($key);
            $this->touchedEnv[$key] = $previous === false ? false : $previous;
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }

    private function unsetEnv(string $key): void
    {
        if (!array_key_exists($key, $this->touchedEnv)) {
            $previous = getenv($key);
            $this->touchedEnv[$key] = $previous === false ? false : $previous;
        }
        putenv($key);
        unset($_ENV[$key]);
    }

    public function testUnsetVariableFallsBackToDefault(): void
    {
        $this->unsetEnv('STEAM_API_URL');

        $this->assertSame(
            'https://api.steampowered.com',
            ConfigHelper::env('STEAM_API_URL', 'https://api.steampowered.com')
        );
    }

    public function testUnsetVariableWithoutDefaultReturnsNull(): void
    {
        $this->unsetEnv('STEAM_API_KEY');

        $this->assertNull(ConfigHelper::env('STEAM_API_KEY'));
    }

    public function testBlankVariableFallsBackToDefault(): void
    {
        $this->setEnv('APP_BASE_URL', '');

        $this->assertSame('http://localhost:8000', ConfigHelper::env('APP_BASE_URL', 'http://localhost:8000'));
    }

    public function testConfiguredValueWinsOverDefault(): void
    {
        $this->setEnv('APP_VERSION', '2.1.0');

        $this->assertSame('2.1.0', ConfigHelper::env('APP_VERSION', '1.0.0'));
    }

    public function testZeroIsAValidConfiguredValue(): void
    {
        $this->setEnv('CACHE_TTL', '0');

        $this->assertSame('0', ConfigHelper::env('CACHE_TTL', '60'));
    }

    public function testBooleanStringsAreConverted(): void
    {
        $this->setEnv('APP_DEBUG', 'TRUE');
        $this->assertTrue(ConfigHelper::env('APP_DEBUG', false));

        $this->setEnv('APP_DEBUG', 'false');
        $this->assertFalse(ConfigHelper::env('APP_DEBUG', true));
    }

    public function testSteamConfigExposesApiUrlFallback(): void
    {
        $this->unsetEnv('STEAM_API_URL');

        $this->assertSame('https://api.steampowered.com', ConfigHelper::steam('api_url'));
    }
}