<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Purestruct\Decoder;
use Purestruct\Seq;
use Countable;

class DecoderTest extends TestCase
{
    public function testDecode(): void
    {
        $this->assertTrue(Decoder::string()->decode('a')->isRight());
        $this->assertTrue(Decoder::string()->decode(1)->isLeft());

        $this->assertTrue(Decoder::int()->decode(1)->isRight());
        $this->assertTrue(Decoder::int()->decode(0.1)->isLeft());
        $this->assertTrue(Decoder::int()->decode('1')->isRight());

        $this->assertTrue(Decoder::float()->decode(1)->isRight());
        $this->assertTrue(Decoder::float()->decode(0.1)->isRight());
        $this->assertTrue(Decoder::float()->decode('1')->isRight());

        $this->assertTrue(Decoder::mixed()->array()->decode([])->isRight());
        $this->assertTrue(Decoder::string()->array()->decode(['1'])->isRight());
        $this->assertTrue(Decoder::string()->array()->decode(['a', 1])->isLeft());

        $this->assertTrue(Decoder::int()->index(1)->decode(['a', 1])->isRight());
        $this->assertTrue(Decoder::int()->index(1)->decode([1, 'a'])->isLeft());

        $this->assertTrue(Decoder::float()->field('a')->decode(['a' => 1])->isRight());
        $this->assertTrue(Decoder::float()->field('a')->decode(['a' => 'a'])->isLeft());

        $this->assertTrue(Decoder::object()->decode((object)['a' => 1])->isRight());
        $this->assertTrue(Decoder::object()->decode(['a' => 1])->isLeft());

        $this->assertTrue(Decoder::string()->required()->decode('a')->isRight());
        $this->assertTrue(Decoder::string()->required()->decode('0')->isRight());
        $this->assertTrue(Decoder::string()->required()->decode('false')->isRight());
        $this->assertTrue(Decoder::bool()->required()->decode(false)->isRight());
        $this->assertTrue(Decoder::int()->required()->decode(0)->isRight());
        $this->assertTrue(Decoder::mixed()->required()->decode(null)->isLeft());
        $this->assertTrue(Decoder::string()->array()->required()->decode([])->isLeft());
        $this->assertTrue(Decoder::int()->array()->required()->decode([0])->isRight());
        $this->assertTrue(Decoder::mixed()->required()->decode((object) [])->isRight());

        $this->assertTrue(
            Decoder::mixed()
                ->required()
                ->decode(
                    new class implements Countable
                    {
                        public function count(): int
                        {
                            return 0;
                        }
                    },
                )
                ->isLeft(),
        );

        $this->assertTrue(
            Decoder::mixed()
                ->required()
                ->decode(
                    new class implements Countable
                    {
                        public function count(): int
                        {
                            return 1;
                        }
                    },
                )
                ->isRight(),
        );

        $this->assertTrue(Decoder::string()->min(2)->decode('ab')->isRight());
        $this->assertTrue(Decoder::string()->min(2)->decode('a')->isLeft());

        $this->assertTrue(Decoder::string()->max(1)->decode('a')->isRight());
        $this->assertTrue(Decoder::string()->max(1)->decode('ab')->isLeft());

        $this->assertTrue(Decoder::string()->email()->decode('test@example.com')->isRight());
        $this->assertTrue(Decoder::string()->email()->decode('testexample.com')->isLeft());

        $this->assertTrue(Decoder::int()->nullable()->decode(null)->isRight());
        $this->assertTrue(Decoder::int()->nullable()->decode('')->isLeft());

        $this->assertTrue(Decoder::bool()->decode(false)->isRight());
        $this->assertTrue(Decoder::bool()->decode('true')->isRight());
        $this->assertTrue(Decoder::bool()->decode('')->isRight());
        $this->assertTrue(Decoder::bool()->decode(0)->isRight());
        $this->assertTrue(Decoder::bool()->decode(1)->isRight());
        $this->assertTrue(Decoder::bool()->decode(2)->isLeft());
        $this->assertTrue(Decoder::bool()->decode('a')->isLeft());

        $oneOf = Decoder::oneOf(Seq::fromArray([Decoder::int(), Decoder::bool()]));
        $this->assertTrue($oneOf->decode('true')->isRight());
        $this->assertTrue($oneOf->decode(2)->isRight());
        $this->assertTrue($oneOf->decode(0.1)->isLeft());
        $this->assertTrue($oneOf->decode('a')->isLeft());
    }
}
