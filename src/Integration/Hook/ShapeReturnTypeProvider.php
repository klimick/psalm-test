<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration\Hook;

use Fp\Functional\Option\Option;
use Klimick\PsalmTest\Integration\Psalm;
use Klimick\PsalmTest\StaticType\StaticTypeInterface;
use Klimick\PsalmTest\StaticType\StaticTypes;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use function Fp\Collection\first;
use function Fp\Evidence\proveTrue;

final class ShapeReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [StaticTypes::class];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Type\Union
    {
        $return_type = Option::do(function() use ($event) {
            yield proveTrue('shape' === $event->getMethodNameLowercase());

            $arg_type = yield first($event->getCallArgs())
                ->flatMap(fn($arg) => Psalm::getArgType($event, $arg))
                ->flatMap(fn($union) => Psalm::asSingleAtomicOf(Type\Atomic\TKeyedArray::class, $union));

            $remapped = [];

            foreach ($arg_type->properties as $key => $type) {
                $remapped[$key] = yield Option::some($type)
                    ->flatMap(fn($union) => Psalm::asSingleAtomicOf(Type\Atomic\TGenericObject::class, $union))
                    ->flatMap(fn($generic) => Psalm::getTypeParam($generic, StaticTypeInterface::class, position: 0));
            }

            $keyed_array = new Type\Atomic\TKeyedArray($remapped);
            $keyed_array->sealed = $arg_type->sealed;
            $keyed_array->is_list = $arg_type->is_list;

            return new Type\Union([
                new Type\Atomic\TGenericObject(StaticTypeInterface::class, [
                    new Type\Union([$keyed_array]),
                ]),
            ]);
        });

        return $return_type->get();
    }
}
