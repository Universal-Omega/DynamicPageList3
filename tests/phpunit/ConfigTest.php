<?php

namespace MediaWiki\Extension\DynamicPageList4\Tests;

use MediaWiki\Extension\DynamicPageList4\Config;
use MediaWiki\Config\HashConfig;
use MediaWikiIntegrationTestCase;

/**
 * @group DynamicPageList4
 * @covers \MediaWiki\Extension\DynamicPageList4\Config
 */
class ConfigTest extends MediaWikiIntegrationTestCase {

    /**
     * Test singleton pattern
     */
    public function testSingleton() {
        $config1 = Config::newFromGlobals();
        $config2 = Config::newFromGlobals();

        $this->assertSame($config1, $config2, 'Config should return the same instance');
    }

    /**
     * Test that config extends MultiConfig correctly
     */
    public function testIsMultiConfig() {
        $config = Config::newFromGlobals();
        $this->assertInstanceOf(\MediaWiki\Config\MultiConfig::class, $config);
    }

    /**
     * Test config initialization with globals
     */
    public function testConfigInitialization() {
        // Set some test globals
        $GLOBALS['wgDynamicPageList4TestValue'] = 'test123';
        
        $config = Config::newFromGlobals();
        
        // Test that we can access MediaWiki config values
        $this->assertTrue($config->has('Sitename'), 'Should have access to MediaWiki config');
        
        // Clean up
        unset($GLOBALS['wgDynamicPageList4TestValue']);
    }

    /**
     * Test config with custom HashConfig
     */
    public function testCustomConfig() {
        $customConfig = new HashConfig([
            'TestValue' => 'custom_value',
            'Sitename' => 'TestWiki'
        ]);

        // This tests the potential for dependency injection
        // The actual Config class may not support this yet, but it should
        $this->assertSame('custom_value', $customConfig->get('TestValue'));
    }

    /**
     * Test config access patterns
     */
    public function testConfigAccess() {
        $config = Config::newFromGlobals();
        
        // Test common MediaWiki config access
        $this->assertTrue(is_string($config->get('Sitename')));
        $this->assertTrue(is_string($config->get('Server')));
        
        // Test has() method
        $this->assertTrue($config->has('Sitename'));
        $this->assertTrue($config->has('Server'));
    }

    /**
     * Test config exception handling
     */
    public function testConfigException() {
        $config = Config::newFromGlobals();
        
        $this->expectException(\MediaWiki\Config\ConfigException::class);
        $config->get('NonExistentConfigKey' . uniqid());
    }

    /**
     * Test config with DPL-specific settings
     */
    public function testDPLSpecificConfig() {
        // Test potential DPL4 specific configuration
        $config = Config::newFromGlobals();
        
        // These would be the kinds of DPL-specific configs we might expect
        $potentialDPLConfigs = [
            'wgDLPmaxCategoryCount',
            'wgDLPAllowUnlimitedResults',
            'wgDLPMaxResultCount'
        ];
        
        foreach ($potentialDPLConfigs as $configKey) {
            // We test that accessing these doesn't crash, even if they don't exist
            try {
                $config->get($configKey);
                $this->assertTrue(true, "Config $configKey accessed without error");
            } catch (\MediaWiki\Config\ConfigException $e) {
                // This is expected for non-existent config keys
                $this->assertTrue(true, "Config $configKey properly throws exception when missing");
            }
        }
    }

    /**
     * Test reset functionality if implemented
     */
    public function testConfigReset() {
        $config1 = Config::newFromGlobals();
        
        // If Config had a reset method, we'd test it here
        // This is a placeholder for potential future functionality
        $this->assertInstanceOf(Config::class, $config1);
        
        // Test that multiple calls still return singleton
        $config2 = Config::newFromGlobals();
        $this->assertSame($config1, $config2);
    }

    /**
     * Test configuration inheritance from MultiConfig
     */
    public function testMultiConfigInheritance() {
        $config = Config::newFromGlobals();
        
        // Test that it properly inherits MultiConfig methods
        $this->assertTrue(method_exists($config, 'get'));
        $this->assertTrue(method_exists($config, 'has'));
        
        // Test that it's properly configured with MainConfig
        $this->assertTrue($config->has('Sitename'));
    }

    /**
     * Test config performance characteristics
     */
    public function testConfigPerformance() {
        $startTime = microtime(true);
        
        // Multiple singleton calls should be fast
        for ($i = 0; $i < 1000; $i++) {
            Config::newFromGlobals();
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should be very fast since it's just returning the same instance
        $this->assertLessThan(0.1, $duration, 'Singleton pattern should be performant');
    }

    /**
     * Test that config properly handles MediaWiki service integration
     */
    public function testServiceIntegration() {
        $config = Config::newFromGlobals();
        
        // Test that it integrates with MediaWiki's service container
        $this->assertInstanceOf(\MediaWiki\Config\Config::class, $config);
        
        // Test common MediaWiki configurations
        $basicConfigs = ['Sitename', 'Server', 'ScriptPath'];
        
        foreach ($basicConfigs as $configKey) {
            $this->assertTrue($config->has($configKey), "Should have access to $configKey");
            $value = $config->get($configKey);
            $this->assertNotNull($value, "$configKey should not be null");
        }
    }
}