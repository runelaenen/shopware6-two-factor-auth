<?php declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import('vendor/shopware/platform/easy-coding-standard.php');
    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::SKIP, [
        AssignmentInConditionSniff::class . '.FoundInWhileCondition',
    ]);
};