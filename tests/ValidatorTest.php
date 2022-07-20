<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Purestruct\Validator;
use Purestruct\Seq;
use Countable;

class ValidatorTest extends TestCase
{
    public function testValidate(): void
    {
        $this->assertTrue(Validator::string()->validate('a')->isSuccess());
        $this->assertTrue(Validator::string()->validate(1)->isFailure());

        $this->assertTrue(Validator::int()->validate(1)->isSuccess());
        $this->assertTrue(Validator::int()->validate(0.1)->isFailure());
        $this->assertTrue(Validator::int()->validate('1')->isSuccess());

        $this->assertTrue(Validator::float()->validate(1)->isSuccess());
        $this->assertTrue(Validator::float()->validate(0.1)->isSuccess());
        $this->assertTrue(Validator::float()->validate('1')->isSuccess());

        $this->assertTrue(Validator::mixed()->array()->validate([])->isSuccess());
        $this->assertTrue(Validator::string()->array()->validate(['1'])->isSuccess());
        $this->assertTrue(Validator::string()->array()->validate(['a', 1])->isFailure());

        $this->assertTrue(Validator::int()->index(1)->validate(['a', 1])->isSuccess());
        $this->assertTrue(Validator::int()->index(1)->validate([1, 'a'])->isFailure());

        $this->assertTrue(Validator::float()->field('a')->validate(['a' => 1])->isSuccess());
        $this->assertTrue(Validator::float()->field('a')->validate(['a' => 'a'])->isFailure());

        $this->assertTrue(Validator::object()->validate((object)['a' => 1])->isSuccess());
        $this->assertTrue(Validator::object()->validate(['a' => 1])->isFailure());

        $this->assertTrue(Validator::string()->required()->validate('a')->isSuccess());
        $this->assertTrue(Validator::string()->required()->validate('0')->isSuccess());
        $this->assertTrue(Validator::string()->required()->validate('false')->isSuccess());
        $this->assertTrue(Validator::bool()->required()->validate(false)->isSuccess());
        $this->assertTrue(Validator::int()->required()->validate(0)->isSuccess());
        $this->assertTrue(Validator::mixed()->required()->validate(null)->isFailure());
        $this->assertTrue(Validator::string()->array()->required()->validate([])->isFailure());
        $this->assertTrue(Validator::int()->array()->required()->validate([0])->isSuccess());
        $this->assertTrue(Validator::mixed()->required()->validate((object) [])->isSuccess());

        $this->assertTrue(
            Validator::mixed()
                ->required()
                ->validate(
                    new class implements Countable
                    {
                        public function count(): int
                        {
                            return 0;
                        }
                    },
                )
                ->isFailure(),
        );

        $this->assertTrue(
            Validator::mixed()
                ->required()
                ->validate(
                    new class implements Countable
                    {
                        public function count(): int
                        {
                            return 1;
                        }
                    },
                )
                ->isSuccess(),
        );

        $this->assertTrue(Validator::string()->min(2)->validate('ab')->isSuccess());
        $this->assertTrue(Validator::string()->min(2)->validate('a')->isFailure());

        $this->assertTrue(Validator::string()->max(1)->validate('a')->isSuccess());
        $this->assertTrue(Validator::string()->max(1)->validate('ab')->isFailure());

        $this->assertTrue(Validator::mixed()->email()->validate('test@example.com')->isSuccess());
        $this->assertTrue(Validator::mixed()->email()->validate('testexample.com')->isFailure());

        $this->assertTrue(Validator::int()->nullable()->validate(null)->isSuccess());
        $this->assertTrue(Validator::int()->nullable()->validate('')->isFailure());

        $this->assertTrue(Validator::bool()->validate(false)->isSuccess());
        $this->assertTrue(Validator::bool()->validate('true')->isSuccess());
        $this->assertTrue(Validator::bool()->validate('')->isSuccess());
        $this->assertTrue(Validator::bool()->validate(0)->isSuccess());
        $this->assertTrue(Validator::bool()->validate(1)->isSuccess());
        $this->assertTrue(Validator::bool()->validate(2)->isFailure());
        $this->assertTrue(Validator::bool()->validate('a')->isFailure());

        $oneOf = Validator::oneOf(Seq::fromArray([Validator::int(), Validator::bool()]));
        $this->assertTrue($oneOf->validate('true')->isSuccess());
        $this->assertTrue($oneOf->validate(2)->isSuccess());
        $this->assertTrue($oneOf->validate(0.1)->isFailure());
        $this->assertTrue($oneOf->validate('a')->isFailure());
    }
}
