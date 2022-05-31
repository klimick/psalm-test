<?php

declare(strict_types=1);

namespace Klimick\PsalmTest\Integration\Hook;

use Fp\Collections\NonEmptyLinkedList;
use Fp\Functional\Option\Option;
use Klimick\PsalmTest\Integration\Psalm;
use Klimick\PsalmTest\StaticType\StaticTypeInterface;
use Klimick\PsalmTest\StaticType\StaticTypes;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use Psalm\Type\Atomic\TGenericObject;
use function Fp\Collection\first;
use function Fp\Evidence\proveTrue;

final class IntersectionReturnTypeProvider implements MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [StaticTypes::class];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Type\Union
    {
        $return_type = Option::do(function() use ($event) {
            yield proveTrue('intersection' === $event->getMethodNameLowercase());

            $types = yield self::getTypes($event);
            $first_type = clone $types->head();

            foreach ($types->tail() as $addToIntersection) {
                $first_type->addIntersectionType($addToIntersection);
            }

            return new Type\Union([
                new TGenericObject(StaticTypeInterface::class, [
                    new Type\Union([$first_type]),
                ]),
            ]);
        });

        return $return_type->get();
    }

    /**
     * @return Option<NonEmptyLinkedList<Type\Atomic\TNamedObject>>
     */
    private static function getTypes(MethodReturnTypeProviderEvent $event): Option
    {
        return Option::do(function() use ($event) {
            $keyed_array = yield first($event->getCallArgs())
                ->flatMap(fn($arg) => Psalm::$args->getArgType($event, $arg))
                ->flatMap(fn($union) => Psalm::$types->asSingleAtomicOf(Type\Atomic\TKeyedArray::class, $union))
                ->filter(fn($keyed_array) => $keyed_array->is_list);

            $types = [];

            foreach ($keyed_array->properties as $property) {
                $types[] = yield Option::some($property)
                    ->flatMap(fn($union) => Psalm::$types->asSingleAtomicOf(Type\Atomic\TGenericObject::class, $union))
                    ->flatMap(fn($generic) => Psalm::$types->getGeneric($generic, StaticTypeInterface::class, position: 0))
                    ->flatMap(fn($union) => Psalm::$types->asSingleAtomicOf(Type\Atomic\TNamedObject::class, $union));
            }

            return NonEmptyLinkedList::collectNonEmpty($types);
        });
    }
}
