<?php

namespace Jacquesbh\tests\units;

// Autoload
require_once __DIR__ . '/../../vendor/autoload.php';

use \mageekguy\atoum;
use \Jacquesbh;

class Console extends atoum\test
{

    public function test__construct()
    {
        $this->assert
            ->object(new Jacquesbh\Console)
            ->isInstanceOf('Jacquesbh\Console')
        ;
    }

    public function testStartSession()
    {
        $this->assert
            ->object($console = new Jacquesbh\Console)
            ->when(function () use ($console) {
                $console->startSession();
            })
            ->string(session_id())
            ->isNotEmpty()
        ;
    }

    public function testChangeDir()
    {
        $this->assert->object($console = new Jacquesbh\Console);
        $this->assert
            ->when(function () use ($console) {
                $console->changeDir('/');
            })
            ->string($console->getPwd())
            ->isEqualTo('/')
        ;
    }

    public function testGetPwd()
    {
        $this->assert
            ->string((new Jacquesbh\Console)->getPwd())
            ->isEqualTo(__DIR__)
        ;
    }

    public function testActiveHttpAuth()
    {
        $class = new \ReflectionClass($console = new \Jacquesbh\Console);
        $property = $class->getProperty('_httpAuthActive');
        $property->setAccessible(true);

        $this->assert
            ->when(function () use ($console) {
                $console->activeHttpAuth(true);
            })
                ->boolean($property->getValue($console))
                    ->isTrue()
            ->when(function () use ($console) {
                $console->activeHttpAuth(false);
            })
                ->boolean($property->getValue($console))
                    ->isFalse()
        ;
    }

    public function testAddAndRemoveUser()
    {
        $console = new \Jacquesbh\Console;

        $class = new \ReflectionClass($console);
        $property = $class->getProperty('_users');
        $property->setAccessible(true);

        $this->assert
            ->when(function () use ($console) {
                $console->addUser('jacquesbh', 'password');
            })
                ->array($property->getValue($console))
                    ->isEqualTo(array('jacquesbh' => 'password'))
            ->when(function () use ($console) {
                $console->removeUser('jacquesbh');
            })
                ->array($property->getValue($console))
                    ->isEmpty()
        ;
    }

    public function testSetGetSession()
    {
        $console = new \Jacquesbh\Console;

        $this->assert
            ->array($console->getSession())
                ->isEmpty()
            ->when(function () use ($console) {
                $console->setSession('foo', 'bar');
            })
                ->array($console->getSession())
                    ->isEqualTo(array('foo' => 'bar'))
        ;
    }

    public function testGetUsername()
    {
        $this->assert
            ->string((new \Jacquesbh\Console)->getUsername())
                ->isEqualTo('console')
        ;
    }

    public function testGetHostname()
    {
        $_SERVER['SERVER_ADDR'] = 'foo';

        $this->assert
            ->string((new \Jacquesbh\Console)->getHostname())
                ->isEqualTo('foo')
        ;

        unset($_SERVER['SERVER_ADDR']);

        $this->assert
            ->string((new \Jacquesbh\Console)->getHostname())
                ->isEqualTo('localhost')
        ;
    }

}
