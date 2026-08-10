<?php

declare(strict_types=1);

namespace Sloukapetr\ComgateLaravelPackage\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sloukapetr\ComgateLaravelPackage\ComgateServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ComgateServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('comgate.merchant_id', 'merchant-123');
        $app['config']->set('comgate.secret', 'secret-xyz');
        $app['config']->set('comgate.test', true);
        $app['config']->set('comgate.base_url', 'https://payments.comgate.cz/v2.0');
        $app['config']->set('comgate.status.base_url', 'https://status.comgate.cz');
        $app['config']->set('comgate.status.timeout', 30);
        $app['config']->set('comgate.webhook.enabled', true);
        $app['config']->set('comgate.webhook.middleware', []);
        $app['config']->set('comgate.webhook.path', '/comgate/webhook');
        $app['config']->set('comgate.webhook.route_name', 'comgate.webhook');
    }
}