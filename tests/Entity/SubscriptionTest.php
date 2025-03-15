<?php
namespace App\Tests\Entity;

use App\Entity\Subscription;
use PHPUnit\Framework\TestCase;

class SubscriptionTest extends TestCase
{
    public function testGetterAndSetter()
    {
        $subscription = new Subscription();

        // Test name
        $name = 'Premium';
        $subscription->setName($name);
        $this->assertEquals($name, $subscription->getName());

        // Test description
        $description = 'Premium subscription';
        $subscription->setDescription($description);
        $this->assertEquals($description, $subscription->getDescription());

        // Test max_pdf
        $maxPdf = 100;
        $subscription->setMaxPdf($maxPdf);
        $this->assertEquals($maxPdf, $subscription->getMaxPdf());

        // Test price
        $price = 99.99;
        $subscription->setPrice($price);
        $this->assertEquals($price, $subscription->getPrice());
    }
}