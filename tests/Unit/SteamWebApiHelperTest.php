<?php
/**
 * Unit Tests for SteamWebApiHelper
 *
 * Deckt die reine URL-Erzeugung sowie die Konfigurationsauflösung von
 * Timeout und User-Agent ab — ohne ausgehende Netzwerkaufrufe.
 *
 * @package stopfen/steam-rest-api-php
 * @author Stopfen
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\SteamWebApiHelper;
use PHPUnit\Framework\TestCase;

class SteamWebApiHelperTest extends TestCase
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

    public function testBuildUrlLeavesParameterlessUrlUntouched(): void
    {
        $helper = new SteamWebApiHelper();

        $this->assertSame(
            'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2',
            $helper->buildUrl('https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2')
        );
    }

    public function testBuildUrlAppendsQueryString(): void
    {
        $helper = new SteamWebApiHelper();

        $this->assertSame(
            'https://api.steampowered.com/x/?key=abc&steamids=1',
            $helper->buildUrl('https://api.steampowered.com/x/', ['key' => 'abc', 'steamids' => '1'])
        );
    }

    public function testBuildUrlUsesAmpersandWhenUrlAlreadyHasQuery(): void
    {
        $helper = new SteamWebApiHelper();

        $this->assertSame(
            'https://example.com/path?format=json&appid=730',
            $helper->buildUrl('https://example.com/path?format=json', ['appid' => 730])
        );
    }

    public function testBuildUrlSkipsEmptyParameterList(): void
    {
        $helper = new SteamWebApiHelper();

        $this->assertStringNotContainsString('?', $helper->buildUrl('https://example.com/path', []));
    }

    public function testDefaultsAreUsedWithoutConfiguration(): void
    {
        $this->setEnv('STEAM_HTTP_TIMEOUT', '');
        $this->setEnv('STEAM_HTTP_USER_AGENT', '');

        $helper = new SteamWebApiHelper();

        $this->assertSame(SteamWebApiHelper::DEFAULT_TIMEOUT, $helper->getTimeout());
        $this->assertSame(SteamWebApiHelper::DEFAULT_USER_AGENT, $helper->getUserAgent());
    }

    public function testTimeoutAndUserAgentAreReadFromEnvironment(): void
    {
        $this->setEnv('STEAM_HTTP_TIMEOUT', '3');
        $this->setEnv('STEAM_HTTP_USER_AGENT', 'steam-api-tester/1.0');

        $helper = new SteamWebApiHelper();

        $this->assertSame(3, $helper->getTimeout());
        $this->assertSame('steam-api-tester/1.0', $helper->getUserAgent());
    }

    public function testExplicitConstructorArgumentsWinOverEnvironment(): void
    {
        $this->setEnv('STEAM_HTTP_TIMEOUT', '3');
        $this->setEnv('STEAM_HTTP_USER_AGENT', 'from-env');

        $helper = new SteamWebApiHelper(null, 7, 'explicit-agent');

        $this->assertSame(7, $helper->getTimeout());
        $this->assertSame('explicit-agent', $helper->getUserAgent());
    }

    public function testInvalidTimeoutFallsBackToDefault(): void
    {
        $this->setEnv('STEAM_HTTP_TIMEOUT', 'not-a-number');

        $helper = new SteamWebApiHelper();

        $this->assertSame(SteamWebApiHelper::DEFAULT_TIMEOUT, $helper->getTimeout());
    }
}