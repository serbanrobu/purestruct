# Purestruct :star:

A library that offers utility functions that consume less-structured input and
produces more-structured output in order to create data structures that makes
illegal states unrepresentable. This facilitates making total functions<sup
id="total-function-ref">[1](#total-function-note)</sup>.

If you don't value total functions, this library might not be for you.

**Table of Contents**

- [Purestruct :star:](#purestruct-star)
  - [Installation :rocket:](#installation-rocket)
  - [Credits :clap:](#credits-clap)
  - [The Problem :thinking:](#the-problem-thinking)
  - [The Solution :bulb:](#the-solution-bulb)
    - [Decoding :hammer:](#decoding-hammer)
    - [Lifting :muscle:](#lifting-muscle)
    - [Validation :guardsman:](#validation-guardsman)
    - [Elimination :fire:](#elimination-fire)
    - [Sum Types :heavy_plus_sign:](#sum-types-heavy_plus_sign)
    - [Bare-bones :skull:](#bare-bones-skull)
  - [Usage in Laravel :toolbox:](#usage-in-laravel-toolbox)
  - [Conclusion :tada:](#conclusion-tada)
  - [License :key:](#license-key)

## Installation :rocket:

You can install the package via composer:

```sh
composer require serbanrobu/purescruct
```

## Credits :clap:

This library was made with _"Parse, don't validate."_ mantra in mind. I highly
recommend reading
[this](https://lexi-lambda.github.io/blog/2019/11/05/parse-don-t-validate/) blog
post.

Many concepts have been applied from the functional programming world such as
_Functor_, _Applicative_, _Semigroup_, _Monoid_, _Monad_ and data structures
such as _Pair_, _List_ (_Seq_), _Map_ (_Dict_), _Either_. Although it is not
necessary to know them in order to use the library, it would certainly help.

The library was highly inspired by _Json.Decode_ module from
[elm/json](https://package.elm-lang.org/packages/elm/json/1.1.3/Json-Decode) Elm
package and [validation](https://hackage.haskell.org/package/validation) Haskell
package.

## The Problem :thinking:

Let's say we have this input:

```php
$input = [
    'name' => 'John Doe',
    'email' => 'johndoe@example.com',
];
```

And we have some function that operate on this input:

```php
function handleUser(array $user): mixed {
    // TODO
}
```

Any input from outside the app should be validated, right? With Laravel
framework you probably would have done something like:

```php
$user = Validator::make(
    data: $input,
    rules: [
        'name' => 'required|max:255',
        'email' => 'required|max:255|email',
    ],
)
    ->validate();

handleUser($user);
```

The function `handleUser` expect to receive as a parameter a user with a certain
valid structure, although its type, `array`, does not say anything about it.
The function allows it to be called with any `array` value that will, probably,
cause an error to be thrown if it does not have the expected form. This means
that the function is [partial](https://en.wikipedia.org/wiki/Partial_function).

Why not include the validation in the `handleUser` function? Would that make it
a total function? What if we had more functions operating on that user?

```php
handleUser1($user);
handleUser2($user);
```

Sould validation be included in both functions in this case? That sounds
inefficient, doesn't it? We have already validated the input but we have no
proof that we did this because we discarded this information. One way to keep
proof of input validation is to encapsulate the validated data in a structure
that makes illegal states unrepresentable.

```php
class User
{
    private function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {
    }

    public function tryFrom(array $input): ?self
    {
        // TODO Validate input

        return new self(
            name: $input['name'],
            email: $input['email'],
        );
    }
}
```

By making the constructor `private` we made sure that the instantiation of the
class is possible only with validated data. Once we have an instance, the
properties no longer be invalidated because we made the structure immutable by
making the properties `readonly`<sup
id="readonly-ref">[2](#readonly-note)</sup>.

Any instance of this class is a proof that we have validated the input. We can
pass this proof to other functions to use it. Now the `handleUser` function
tells us more about what it needs to work.

```php
function handleUser(User $user): mixed
{
    // TODO
}
```

We can have several functions that use the same proof without creating it again.
What if we have another function that doesn't need the whole user but only his
email?

```php
function handleEmail(string $email): mixed
{
    // TODO
}
```

Can we do this?

```php
handleEmail($user->email);
```

We could. But in doing so we lose the proof that this email is valid and the
`handleEmail` function would not be total. We can fix this by breaking our proof
into smaller ones. Namely, using **value objects**.

```php
class NonEmptyString
{
    private function __construct(public readonly string $inner)
    {
    }

    public function tryFrom(string $input): ?self
    {
        // TODO
    }
}

class Max255NonEmptyString
{
    private function __construct(public readonly NonEmptyString $inner)
    {
    }

    public function tryFrom(NonEmptyString $input): ?self
    {
        // TODO
    }
}

class Email
{
    private function __construct(public readonly Max255NonEmptyString $inner)
    {
    }

    public function tryFrom(Max225NonEmptyString $input): ?self
    {
        // TODO
    }
}

class User
{
    public function __construct(
        public Max255NonEmptyString $name,
        public Email $email,
    ) {
    }
}
```

Lots of code, I know. The good thing is that if these classes are not very
specific, they can be reused for many structures or other value objects. Another
good thing we can see is that the `User` class should no longer have the
constructor `private` nor be immutable (although it would provide other
benefits<sup
id="immutability-benefits-ref">[3](#immutability-benefits-note)</sup>) because
any user instance is valid even if we change its state. We can now pass these
value objects anywhere in the application without losing the proof of their
validation and we can clearly express the input of a function.

```php
function handleEmail(Email $email): mixed
{
    // TODO
}
```

Many value objects are string based. To work more efficiently, you may want to
implement the [Stringable](https://www.php.net/manual/en/class.stringable.php)
interface.

What about validation? Can we use Laravel validator or something similar? We
could, but it would be very annoying. Why? One reason not to use Laravel
validator would be that the validator expected to receive an array as input not
a value string/int/bool/etc. So it is not made to validate value objects. Sure,
you can emulate that this way:

```php
Validate::make(
    data: ['value' => $input],
    rules: ['value' => '...'],
);
```

but then the errors would be specific to the `value` field. And what do we do
with all the exceptions? We should catch the `Validation` exception from all
validators and then collect the errors and throw a new exception with all the
errors.

```php
throw new ValidationException::withMessages($mergedErrors);
```

Sounds like a lot of work and lots of `try-catch`.

## The Solution :bulb:

### Decoding :hammer:

This is where `Decoder` comes into play. A decoder knows how to "decode" a
certain type of value. Decoders already exist for most builtin PHP types but
custom decoders can be created.

For example this is a decoder that know how to decode a `int`:

```php
function decodeInt(mixed $value): Either
{
    $decoder = Decoder::int();
    return $decoder->decode($value);
}
```

The result of decoding in this case can either be

```php
Either::left(Error::failure('Expecting an INT'))
```

or

```php
Either::right($value)
```

The user name and email can be decoded using the following decoders:

```php
$nameDecoder = Decoder::string()->required()->max(255);
$emailDecoder = Decoder::string()->email();
```

### Lifting :muscle:

What if we want to validate multiple fields at the same time? We can "lift" a
function in the context of `Decoder`.

For example, the user could be decoded using the following decoder:

```php
$createUser = fn ($name) => fn ($email) => new User($name, $email);

$userDecoder = Decoder::pure($createUser)
    ->apply($nameDecoder->field('name'))
    ->apply($emailDecoder->field('email'));

// Or simpler:
$userDecoder = Decoder::lift(
    $createUser,
    $nameDecoder->field('name'),
    $emailDecoder->field('email'),
);
```

The `pure` function puts a value in the `Decoder` context. Note that the
function must be [curried](https://en.wikipedia.org/wiki/Currying). :curry:

```php
Decoder::pure('foo')->decode('bar');
// Same as
Either::right('foo');
```

The `apply` function will work only on a function decoder and works as follows:

```php
Decoder::pure(fn ($x) => $x * 2)->apply(Decoder::pure(3));
// Same as
Decoder::pure(6);
```

### Validation :guardsman:

Note that when decoding, the decoder will stop at the first error. If we want to
decode a multi-field structure and collect errors from all fields, `Validator`
is the way to go. `Validator` works very similar to `Decoder` but will not stop
at the fist error it encounters, but instead will collect all the errors when
the `apply` (or indirect `lift`) function is used. Instead of `decode` function,
you need to use `validate`. This function will return a `Validation` instance
which can be either

```php
Validation::failure($errors)
```

or

```php
Validation::success($value);
```

where errors is a `Seq` (sequence) of `Error`. All of the above examples work
similarly for `Validator`.

If we wanted to validate an enum we could create a validator for it:

```php
enum Suit
{
    case Hearts;
    case Diamonds;
    case Clubs;
    case Spades;

    public static function validator(): Validator
    {
        return Validator::string()
            ->map(fn ($val) => Str::trim($val))
            ->bind(
                fn ($val) => match ($val) {
                    'hearts' => Validator::succeed(self::Hearts),
                    'diamonds' => Validator::succeed(self::Diamonds),
                    'clubs' => Validator::succeed(self::Clubs),
                    'spades' => Validator::succeed(self::Spades),
                    default => Validator::fail('It\'s not a valid suit case'),
                },
            );
    }
}
```

We can modify the validator using `map` method or chaing them using `bind`. The
functions `succeed` and `fail` are used to ignore the input and produce a
certain value or failure.

Here is an example of a UUID class an its validator:

```php
class Uuid
{
    private function __construct(public readonly string $inner)
    {
    }

    public static function validator(): Validator
    {
        return Validator::string()->bind(
            fn ($val) => Str::isUuid($val)
                ? Validator::succeed(new self($val))
                : Validator::fail('It\'s not a valid UUID'),
        );
    }
}
```

This is a complete example of the user class we created above and the value
objects including validators.

```php
class NonEmptyString
{
    use Validatable;

    private function __construct(public readonly string $inner)
    {
    }

    public static function validator(): Validator
    {
        return Validator::string()->required()->map(self::curry());
    }
}

class Max255NonEmptyString
{
    use Validatable;

    private function __construct(public readonly NonEmptyString $inner)
    {
    }

    public static function validator(NonEmptyString $input): Validator
    {
        return NonEmptyString::validator()->max(255)->map(self::curry());
    }
}

class Email
{
    use Validatable;

    private function __construct(public readonly Max255NonEmptyString $inner)
    {
    }

    public static function validator(Max225NonEmptyString $input): Validator
    {
        return Max255NonEmptyString::validator()->email()->map(self::curry());
    }
}

class User
{
    use Validatable;

    public function __construct(
        public Max255NonEmptyString $name,
        public Email $email,
    ) {
    }

    public static function validator(): Validator
    {
        return Validator::lift(
            self::curry(),
            Max255NonEmptyString::validator()->field('name'),
            Email::validator()->field('email'),
        );
    }
}
```

The `Curry` trait is included in `Validatable` and provides `curry` function
which returns the class constructor function curried.

```php
User::curry();
// Same as
fn ($name) => fn ($email) => new User($name, $email);
```

The `Validatable` trait provides `validate` function which is a shortcut taking
the validator from the instance and than validate.

```php
User::validate($input);
// Same as
User::validator()->validate($input);
```

Note that the validator for a structure that has as fields other structures /
value objects that are validatable is quite straightforward so that it can be
automated using [reflection](https://www.php.net/manual/en/book.reflection.php).
In fact, that's exactly what the trait `Validatable` does. Provides a default
implementation for the `validator` function but expects all fields in the
structure to be validatable, otherwise it will give an error. So we can omit
the validator implementation for the User class. The structure may also contain
nested validatable structures. For nested structures, the `Validatable` trait
does not even need to be included.

For exmple:

```php
class Address
{
    public function __construct(
        Max255NonEmptyString $line1,
        Max255NonEmptyString $line2,
    ) {
    }
}

class User
{
    use Validatable;

    public function __construct(
        Uuid $id,
        Max255NonEmptyString $name,
        Email $email,
        Address $address,
    ) {
    }
}
```

Maybe you want to create an array of users. Unfortunately there are no generics
in PHP and this means that you will have to create a separate class for each
type of collection. A simple structure that contains only validated users can be
defined as follows.

```php
class UserArray
{
    use Validatable;

    private function __construct(public readonly array $inner)
    {
    }

    public static function validator(): Validator
    {
        return User::validator()->array()->map(self::curry());
    }
}
```

### Elimination :fire:

How do we get the value from `Validation`? One way is to match on it.

```php
$input = [
    'name' => 'John Doe',
    'email' => 'johndoe@example.com',
];

$validation = User::validate($input);

/** @var ?User */
$user = $validation->match(
    success: fn (User $user) => $user,
    failure: fn (Seq $errors) => null,
);
```

If we are sure that the validation will be successful or we just want to
experiment with this library we can use the `unwrap` function which will return
the validated value if the validation was successful or otherwire it will throw
an exception with an explicit message telling us why the validation failed.

```php
/**
 * @throws Exception
 * @var User
 */
$user = $validation->unwrap();
```

### Sum Types :heavy_plus_sign:

What if we needed a union of data types (aka [Sum
Types](https://en.wikipedia.org/wiki/Tagged_union))? PHP does not support this
but one way to emulate this is through inheritance. For example, let's say we
need a structure to store an IP address that can be v4 or v6.

```php
abstract class IpAddr
{
}

class V4 extends IpAddr
{
    private function __construct(public readonly string $inner)
    {
    }
}

class V6 extends IpAddr
{
    private function __construct(public readonly string $inner)
    {
    }
}
```

The example above is a common thing to do but we have a problem. If a function
were to receive an `IpAddr` as a parameter we could expect anything because we
can create as many derived classes as we want and this means that it is
impossible to be sure that you cover all cases trying to check what type of
instance is with `instanceof`, unless you have a fallback.

A better approach, in my opinion, would be something like this:

```php
 class V4
{
    use Validatable;

    private function __construct(public readonly string $inner)
    {
    }

    public static function validator(): Validator
    {
        return Validator::string()->bind(
            fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ? Validator::succeed(new self($ip))
                : Validator::fail('It\'s not a valid IPv4 address'),
        );
    }
}

class V6
{
    use Validatable;

    private function __construct(public readonly string $inner)
    {
    }

    public static function validator(): Validator
    {
        return Validator::string()->bind(
            fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                ? Validator::succeed(new self($ip))
                : Validator::fail('It\'s not a valid IPv6 address'),
        );
    }
}

class IpAddr
{
    use EnumValidatable;

    public function __construct(public V4|V6 $inner)
    {
    }
}
```

In this way it is clear with the variants we ar dealing with. Note that you
cannot use `Validatable` when dealing with union types, but `EnumValidatable` is
made specifically for this purpose. We can now validate input similar to this:

```php
IpAddr::validate(['V4' => '127.0.0.1']);
IpAddr::validate(['V6' => '::1']);
```

The name of the trait contains the name _Enum_ because it was inspired by Rust
enums. [Here](https://doc.rust-lang.org/book/ch06-01-defining-an-enum.html) is
the similar example written in Rust.

If you want to enumerate 4 bytes instead of parsing a string for IP v4 variant
you could do as follows.

```php
class UInt8
{
    use Validatable;

    private function __construct(public readonly int $inner)
    {
    }

    public static function validator(): Validator
    {
        return Validator::int()->bind(
            fn (int $byte) => $byte >= 0 && $byte <= 255
                ? Validator::succeed(self::curry())
                : Validator::fail('The byte is out of range'),
        );
    }
}

class V4
{
    use TupleValidatable;

    public function __construct(
        public UInt8 $first,
        public UInt8 $second,
        public UInt8 $third,
        public UInt8 $fourth,
    ) {
    }
}
```

What `TupleValidatable` does you may want to ask. If we had used `Validatable`
we would have created an IPv4 like this:

```php
IpAddr::validate([
    'V4' => [
        'first' => 127,
        'second' => 0,
        'third' => 0,
        'fourth' => 1,
    ],
]);
```

It would be much easier to create it the following way, wouldn't it?

```php
IpAddr::validate(['V4' => [127, 0, 0, 1]]);
```

This is exactly what `TupleValidatable` lets us do. Namely, to ignore the names
of the fields.

This way of representing union types as JSON was inspired by the Rust package
called [Serde](https://serde.rs/json.html#structs-and-enums-in-json).

### Bare-bones :skull:

Most examples involved creating a structure for representing the valid input.
But this is not mandatory. For example, if you want to create an IP validator
as above without creating classes you can do it as follows.

```php
$byteValidator = Validator::int()->bind(
    fn ($byte) => $byte >= 0 && $byte <= 255
        ? Validator::succeed($byte)
        : Validator::fail('The byte is out of range'),
);

$ipV4Validator = Validator::lift(
    fn ($b0) => fn ($b1) => fn ($b2) => fn ($b3) => [$b0, $b1, $b2, $b3],
    $byteValidator->index(0),
    $byteValidator->index(1),
    $byteValidator->index(2),
    $byteValidator->index(3),
);

$ipV6Validator = Validator::string()->bind(
    fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ? Validator::succeed($ip)
        : Validator::fail('It\'s not a valid IPv6 address'),
);

$ipValidator = Validator::oneOf(
    Seq::fromArray([
        $ipV4Validator->field('V4'),
        $ipV6Validator->field('V6'),
    ]),
);
```

## Usage in Laravel :toolbox:

Let's say we want to validate the request with our validator in a user
controller. Let's assume we've already defined a validator for `UserForm`.

```php
class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validation = UserForm::validate($request->all());

        return $validation->match(
            success: function (UserForm $form) {
                $user = User::create($form->toArray());

                return response()->json($user);
            },
            failure: function (Seq $errors) {
                return response()->json([
                    'errors' => [
                        // TODO
                    ],
                ]);
            },
        );
    }
}
```

We know what to do if the input has been successfully validated. But what do we
do if it fails? We have the `Seq` of `Error` to do what we want with them. An
error contains a lot of useful information about why the validator failed. But
you may be accustomed to the errors generated by Laravel Validator and you don't
want to bother creating custom errors. Or you may want to use the Blade
directives to display errors for a specific input field. No problem. We can
emulate our errors as if they were generated by the Laravel Validator similar
to the following example.

```php
function unwrapValidation(Validation $validation): mixed
{
    return $validation->unwrapOrElse(
        fn (Seq $errors) => throw ValidationException::withMessages(
            Dict::fromSeq(
                $errors->map(function (Error $error) {
                    $pair = $error->toPair();

                    $pair->first = Str::of($pair->first)
                        ->replace('[', '.')
                        ->replace(']', '');

                    return $pair;
                }),
            )
                ->toArray(),
        ),
    );
}
```

You can use this package similar to the way you use Laravel Validator. Note that
the `Error` `Seq` contains more information than the message array we converted
it to. This means that in some cases the error message may not be very specific
or clear for a particular field. But of course we can change this conversion
function as we wish.

Using the function defined above simplifies things a bit:

```php
class UserController extends Controller
{
    public function store(Request $request): User
    {
        $validation = UserForm::validate($request->all());
        $form = unwrapValidation($validation);

        return User::create($form->toArray());
    }
}
```

The code for validating the input from the request and converting it into a
valid structure is likely to be the same for all `Validatable` structures.
Let's automate things a bit and use the Laravel [Service
Container](https://laravel.com/docs/master/container) to validate the request
input the moment when injecting a specific `Validatable` instance.

```php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $validatableClassNames = [
            UserForm::class,
        ];

        foreach ($validatableClassNames as $className) {
            $this->app->bind($className, function () {
                unwrapValidation($className::validate(request()->all()));
            });
        }
    }
}
```

Of course you can create a specific provider for this if you want. Now the code
in the controller is nice and clean.

```php
class UserController extends Controller
{
    public function store(UserForm $form): User
    {
        return User::create($form->toArray());
    }
}
```

## Conclusion :tada:

In the examples above you can see how easy it is to combine different validators
to create very complex structures without worrying about collecting errors if
any validator has failed in the process. It's so composable that if feels like
LEGO. Although not mandatory, this package helps to easily create structures
that do not allow invalid states. There are already many validators for
different types of data in the package but it certainly won't cover all cases.
You can easily create some custom ones and combine them to form a validator for
a much more complex structure, which may not even have been possible to validate
with a validator like the one in Laravel framework.

## License :key:

MIT

---

-   <sup id="total-function-note">[1](#total-function-ref)</sup>A **total
    function** is a function which is defined for all inputs of the right type,
    that is, for all of a domain.

-   <sup id="readonly-note">[2](#readonly-ref)</sup>You can emulate `readonly`
    feature in older PHP versions by making the property `private` and create a
    _getter_ for it.

-   <sup
    id="immutability-benefits-note">[3](#immutability-benefits-ref)</sup>Among
    the benefits offered by immutability are: it makes the code more testable,
    readable, allows you to reason more easily about the code, it helps making
    illegal states unrepresentable, thread-safety, etc.

---

PS: I hate PHP :trollface:
