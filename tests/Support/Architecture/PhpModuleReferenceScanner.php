<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RuntimeException;

final class PhpModuleReferenceScanner
{
    /**
     * @return list<string>
     */
    public function referencesInFile(string $path): array
    {
        $code = file_get_contents($path);

        if (! is_string($code)) {
            throw new RuntimeException(sprintf('Unable to read PHP architecture input [%s].', $path));
        }

        return $this->referencesInCode($code);
    }

    /**
     * @return list<string>
     */
    public function referencesInCode(string $code): array
    {
        $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);

        if ($statements === null) {
            return [];
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver(null, [
            'preserveOriginalNames' => true,
            'replaceNodes' => false,
        ]));
        $statements = $traverser->traverse($statements);
        $references = [];

        foreach ((new NodeFinder)->findInstanceOf($statements, Node\Stmt\Use_::class) as $useStatement) {
            foreach ($useStatement->uses as $use) {
                $reference = $use->name->toString();

                if (str_starts_with($reference, 'App\\')) {
                    $references[] = $reference;
                }
            }
        }

        foreach ((new NodeFinder)->findInstanceOf($statements, Node\Stmt\GroupUse::class) as $groupUse) {
            foreach ($groupUse->uses as $use) {
                $reference = $groupUse->prefix->toString().'\\'.$use->name->toString();

                if (str_starts_with($reference, 'App\\')) {
                    $references[] = $reference;
                }
            }
        }

        foreach ((new NodeFinder)->findInstanceOf($statements, Node\Name::class) as $name) {
            $resolved = $name->getAttribute('resolvedName');
            $reference = $resolved instanceof Node\Name ? $resolved->toString() : $name->toString();

            if (str_starts_with($reference, 'App\\')) {
                $references[] = $reference;
            }
        }

        foreach ((new NodeFinder)->findInstanceOf($statements, Node\Scalar\String_::class) as $string) {
            if (preg_match_all('/App\\\\[A-Za-z0-9_\\\\]+/', $string->value, $matches) < 1) {
                continue;
            }

            foreach ($matches[0] as $reference) {
                $references[] = $reference;
            }
        }

        sort($references);

        return array_values(array_unique($references));
    }
}
