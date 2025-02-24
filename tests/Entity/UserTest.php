<?php
namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetterAndSetter()
    {
        $user = new User();

        // Test email
        $email = 'test@test.com';
        $user->setEmail($email);
        $this->assertEquals($email, $user->getEmail());

        // Test lastname
        $lastname = 'Doe';
        $user->setLastname($lastname);
        $this->assertEquals($lastname, $user->getLastname());

        // Test firstname
        $firstname = 'John';
        $user->setFirstname($firstname);
        $this->assertEquals($firstname, $user->getFirstname());

        // Test password
        $password = 'securepassword';
        $user->setPassword($password);
        $this->assertEquals($password, $user->getPassword());
    }
}