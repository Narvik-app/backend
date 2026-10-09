<?php

namespace App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration;

enum VehicleEngineType: string {
    case PETROL = 'petrol';
    case DIESEL = 'diesel';
    case ELECTRIC = 'electric';
    case HYBRID = 'hybrid';
}
