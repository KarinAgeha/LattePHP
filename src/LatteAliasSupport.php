<?php

namespace Latte;

class LatteAliasSupport
{
    private array $aliases = [];
    public static function aliasIgniter(Latte $latte)
    {
        $lateAlias = new static;
        $aliases = $latte->getAliases();
        $lateAlias->aliases = $aliases;
        spl_autoload_register([$lateAlias, 'loadAlias'], true, true);
    }

    public function loadAlias(string $alias)
    {
        if(isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }
    }
}