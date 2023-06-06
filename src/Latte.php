<?php
declare(strict_types=1);

namespace Latte;

class Latte
{
    // dependencies
    private array $dependencies = [];
    // created instance
    private array $instances = [];
    // singleton
    private array $singleton = [];

    // If true, name resolution is attempted even if the class is not registered in the DI container.
    private bool $isStrictResolveMode = true;

    // Instance Creation
    public static function Start()
    {
        return new static;
    }

    // Set strict dependency resolution mode
    public function strictResolve()
    {
        $this->isStrictResolveMode = true;
        return $this;
    }

    // Set lenient dependency resolution mode
    public function lenientResolve()
    {
        $this->isStrictResolveMode = false;
        return $this;
    }

    // Register functions and classes to containers as singletons
    public function singletonEntry(string $abstract, string | \Closure $target)
    {
        if(!$this->duplicationCheck($abstract)) {
            throw new LatteException('abstract "'.$abstract.'" is already entered.');
        }

        $this->dependencies[$abstract] = $target;
        return $this;
    }

    // If the same class or function has already been registered, overwrite it and register it
    public function ifOverWriteSingletonEntry(string $abstract, string | object $target)
    {
        unset($this->dependencies[$abstract]);
        unset($this->instances[$abstract]);
        $this->singleton[$abstract] = $target;
        return $this;
    }

    // If the same class or function is already registered, it is not registered.
    public function isIgnoreSingletonEntry(string $abstract, $target)
    {
        if(!$this->duplicationCheck($abstract)) {
            $this->singleton[$abstract] = $target;
        }
        return $this;
    }

    // Register an instance
    public function instanceEntry(string $abstract, object $target)
    {
        if(!$this->duplicationCheck($abstract)) {
            throw new LatteException('abstract "'.$abstract.'" is already entered.');
        }

        $this->instances[$abstract] = $target;
        return $this;
    }

    // If the same class is registered, overwrite it and register it
    public function isOverWriteInstanceEntry(string $abstract, object $target)
    {
        unset($this->dependencies[$abstract]);
        unset($this->singleton[$abstract]);
        $this->instances[$abstract] = $target;
        return $this;
    }

    // If the same class is registered, it is not registered
    public function isIgnoreOverWriteInstanceEntry(string $abstract, object $target)
    {
        if(!$this->duplicationCheck($abstract)) {
            $this->instances[$abstract] = $target;
        }
        return $this;
    }

    // Normal registration
    public function entry(string $abstract, string | \Closure $target)
    {
        if(!$this->duplicationCheck($abstract)) {
            throw new LatteException('abstract "'.$abstract.'" is already entered.');
        }

        $this->dependencies[$abstract] = $target;
        return $this;
    }

    public function ifOverWriteEntry(string $abstract, $target)
    {
        $this->dependencies[$abstract] = $target;
        return $this;
    }

    public function ifIgnoreEntry(string $abstract, $target)
    {
        if(!isset($this->dependencies[$abstract])) {
            $this->dependencies[$abstract] = $target;
        }
        return $this;
    }

    // Instantiation in case of a class, immediate execution in case of a closure
    public function ignite(mixed $abstract)
    {
        return $this->createOrCall($abstract, [], $this->isStrictResolveMode);
    }

    // Pass specific arguments at instantiation or function execution
    public function igniteWithArg(mixed $abstract, array $args)
    {
        return $this->createOrCall($abstract, $args, $this->isStrictResolveMode);
    }

    // Check for duplicate registrations
    private function duplicationCheck(string $abstract)
    {
        return (!isset($this->dependencies[$abstract]) && !isset($this->singleton[$abstract]) && !isset($this->instances[$abstract]));
    }

    // No instantiation or passing an instance to execute the specified method
    public function wakeMethod(string | object $instanceOrName, string $method, array $methodArguments = [], array $instanceArgument = [])
    {
        if(is_string($instanceOrName)) {
            $instance = $this->igniteWithArg($instanceOrName, $instanceArgument);
        }

        $reflectionMethod = new \ReflectionMethod($instance, $method);
        if(is_null($reflectionMethod)) {
            throw new LatteException('Failed to resolve method "'.$method.'".');
        }

        $resolvedArguments = $this->dependenceResolver($reflectionMethod, $methodArguments);

        $reflectionMethod->invokeArgs($instance, $resolvedArguments);
    }

    // Instance creation or function execution
    private function createOrCall(mixed $abstract, array $arguments = [], bool $strictResolve = true)
    {
        $target = $this->get($abstract);

        if(is_null($target[1]) && !$strictResolve) {
            $reflection = $this->returnReflection($abstract);
        } else if(!is_null($target[1])) {
            $reflection = $this->returnReflection($target[1]);
        } else {
            throw new LatteException('In strict dependency resolution mode, it is not possible to resolve dependencies of unregistered classes. Setting the third argument "$strictResolve" to false allows unregistered class dependencies to be resolved.');
        }
        $targetMethodOrFunction = $reflection;
        if($reflection instanceof \ReflectionClass){
            $targetMethodOrFunction = $targetMethodOrFunction->getConstructor();
        }

        $resolvedArguments = [];

        if(!is_null($targetMethodOrFunction)) {
            $resolvedArguments = $this->dependenceResolver($targetMethodOrFunction, $arguments);
        }


        if($target[0] === 'instance') {
            $instance = $target[1];
        } else {
            if($reflection instanceof \ReflectionClass) {
                $instance = $reflection->newInstanceArgs($resolvedArguments);
            } else {
                $reflection->invokeArgs($resolvedArguments);
                return true;
            }
        }

        if(is_null($instance)) {
            throw new LatteException('Latte failed to create the target or dependent instance "'.$reflection->getName().'".');
        }
        if($target[0] === 'singleton') {
            unset($this->singleton[$abstract]);
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    // Resolve argument dependencies
    private function dependenceResolver(\ReflectionFunction | \ReflectionMethod $targetMethodOrFunction, array $arguments = []) : array
    {
        $resolvedArguments = [];

        $targetParameters = $targetMethodOrFunction->getParameters();
        foreach($targetParameters as $params) {
            $typeHinting = $params->getType();
            if(is_null($typeHinting)) {
                if(!is_null($arguments[$params->name])) {
                    $resolvedArguments[] = $arguments[$params->name];
                } else {
                    throw new LatteException('Latte attempted to resolve the unknown type argument but failed. If you want to pass arguments, assign an associative array with values in the keys corresponding to the argument names to the second argument of resolveDependencies.');
                }
            } else {
                if($typeHinting->isBuiltin()) {
                    throw new LatteException('Latte does not have a solution for PHP embedded types.');
                }

                if(isset($this->dependencies[$typeHinting->getName()])) {
                    $resolvedArguments[] = $this->createOrCall($typeHinting->getName());
                } else {
                    throw new LatteException('The class or function "'.$typeHinting->getName().'" is not registered in the latte, so there is no dependency resolution method; register it in the entry method.');
                }
            }
        }

        return $resolvedArguments;
    }

    // Obtaining registered classes, instances, and functions
    private function get(string $abstract)
    {
        if(isset($this->dependencies[$abstract])) {
            return ['dependence', $this->dependencies[$abstract]];
        } else if(isset($this->singleton[$abstract])) {
            return ['singleton', $this->singleton[$abstract]];
        } else if(isset($this->instances[$abstract])) {
            return ['instance', $this->instances[$abstract]];
        }
        return [null, null];
    }

    // Determine if it is a closure
    private function isClosure(mixed $target)
    {
        return is_callable($target);
    }

    // Determine if the instance
    private function isInstance(mixed $target)
    {
        return is_object($target);
    }

    // Return the reflection object corresponding to the class closure
    private function returnReflection(mixed $target)
    {
        if($this->isClosure($target)) {
            return new \ReflectionFunction($target);
        } else if($this->isInstance($target) || is_string($target)) {
            return new \ReflectionClass($target);
        } else {
            throw new LatteException('The latte tried unsuccessfully to resolve the dependence on an unknown type of object.');
        }
    }
}