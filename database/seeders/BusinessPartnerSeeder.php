<?php

namespace Database\Seeders;

use App\Models\BusinessPartner;
use Illuminate\Database\Seeder;

class BusinessPartnerSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [
            ['name' => 'Acme Corporation',      'email' => 'contact@acme.com',        'phone' => '+216 71 123 456', 'status' => 'active',   'type' => 'customer'],
            ['name' => 'Global Supplies Ltd',    'email' => 'info@globalsupplies.com', 'phone' => '+216 71 234 567', 'status' => 'active',   'type' => 'supplier'],
            ['name' => 'TechVision Partners',    'email' => 'hello@techvision.io',     'phone' => '+216 71 345 678', 'status' => 'active',   'type' => 'both'],
            ['name' => 'Mediterranean Trading',  'email' => 'sales@medtrading.tn',     'phone' => '+216 71 456 789', 'status' => 'active',   'type' => 'customer'],
            ['name' => 'Atlas Industries',       'email' => 'info@atlas-ind.com',      'phone' => '+216 71 567 890', 'status' => 'inactive', 'type' => 'supplier'],
            ['name' => 'Sahara Logistics',       'email' => 'ops@saharalog.com',       'phone' => '+216 71 678 901', 'status' => 'active',   'type' => 'customer'],
            ['name' => 'Carthage Electronics',   'email' => 'sales@carthage-elec.tn',  'phone' => '+216 71 789 012', 'status' => 'active',   'type' => 'both'],
            ['name' => 'Olive Branch Exports',   'email' => 'export@olivebranch.tn',   'phone' => '+216 71 890 123', 'status' => 'active',   'type' => 'supplier'],
            ['name' => 'Digital Wave Solutions',  'email' => 'hello@digitalwave.io',    'phone' => '+216 71 901 234', 'status' => 'inactive', 'type' => 'customer'],
            ['name' => 'Prime Materials Co',     'email' => 'pm@primematerials.com',   'phone' => '+216 71 012 345', 'status' => 'active',   'type' => 'supplier'],
            ['name' => 'Jasmine Services',       'email' => 'support@jasmine-svc.tn',  'phone' => '+216 72 123 456', 'status' => 'active',   'type' => 'customer'],
            ['name' => 'Blue Star Shipping',     'email' => 'info@bluestarship.com',   'phone' => '+216 72 234 567', 'status' => 'active',   'type' => 'both'],
            ['name' => 'Fennec Technologies',    'email' => 'dev@fennectech.io',       'phone' => '+216 72 345 678', 'status' => 'active',   'type' => 'supplier'],
            ['name' => 'Horizon Consulting',     'email' => 'consult@horizon.tn',      'phone' => '+216 72 456 789', 'status' => 'inactive', 'type' => 'customer'],
            ['name' => 'Zephyr Industrial',      'email' => 'contact@zephyr-ind.com',  'phone' => '+216 72 567 890', 'status' => 'active',   'type' => 'supplier'],
            ['name' => 'Tunisian Auto Parts',    'email' => 'sales@tunautoparts.tn',   'phone' => '+216 72 678 901', 'status' => 'active',   'type' => 'both'],
            ['name' => 'Phoenix Distribution',   'email' => 'orders@phoenix-dist.com', 'phone' => '+216 72 789 012', 'status' => 'active',   'type' => 'customer'],
            ['name' => 'Cedar Wood Imports',     'email' => 'import@cedarwood.tn',     'phone' => '+216 72 890 123', 'status' => 'active',   'type' => 'supplier'],
            ['name' => 'Nova Engineering',       'email' => 'projects@nova-eng.com',   'phone' => '+216 72 901 234', 'status' => 'inactive', 'type' => 'customer'],
            ['name' => 'Silver Coast Marine',    'email' => 'marine@silvercoast.tn',   'phone' => '+216 72 012 345', 'status' => 'active',   'type' => 'both'],
        ];

        foreach ($partners as $partner) {
            BusinessPartner::create($partner);
        }
    }
}
