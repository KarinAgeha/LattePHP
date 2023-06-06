# Latte
Dependency injection container for PHP 

# Usage

```php
<?php
use Latte;

class A {
    private $b_instance;
    public function __construct(B $b_instance) {
        echo "A instance created!;\n"
       $this->b_instance = $b_instance;
    }
    
    public function index(C $c_instance)
    {
        $c_instance->safety_say($this->b_instance->hello_world())
    }
    
    public function say_welcome()
    {
        echo 'welcome!';
    }
}

class B {
    public function __construct() {
        echo "B instance created!\n"    
    }
    
    public function hello_world() 
    {
        return 'Hello, World!';    
    }
}

class C {
    public function safety_say(string $text) 
    {
        echo htmlspecialchars($text);
    }
}
$latte = Latte::Start()->entry(A::class, A::class)->entry(B::class, B::class)->entry(C::class, C::class)->entry('d_func', function(A $a_instance) {$a_instance->say_welcome();});

// Instance create
$a_instance = $latte->ignite(A::class); // => B instance created! \n A instance created!
$latte->wakeMethod($a_instance, 'index'); // => Hello, World!
// Immediate execution
$latte->ignite('d_func'); // => welcome!
```