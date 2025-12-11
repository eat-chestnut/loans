<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Attributes\Description;
use BenSampo\Enum\Enum;

/**
 * @method static static HOUSE()
 * @method static static GARAGE()
 */
final class CollateralType extends Enum
{

    #[Description('房产')]
    const HOUSE = 1;

    #[Description('车位')]
    const GARAGE = 2;
}
