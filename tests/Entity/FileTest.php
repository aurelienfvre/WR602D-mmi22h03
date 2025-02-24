<?php
namespace App\Tests\Entity;

use App\Entity\File;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;  // Ajoutez cette ligne

class FileTest extends TestCase
{
    public function testGetterAndSetter()
    {
        $file = new File();

        // Test name
        $name = 'document.pdf';
        $file->setName($name);
        $this->assertEquals($name, $file->getName());

        // Test created_at
        $createdAt = new DateTimeImmutable();  // Changé ici
        $file->setCreatedAt($createdAt);
        $this->assertEquals($createdAt, $file->getCreatedAt());
    }
}