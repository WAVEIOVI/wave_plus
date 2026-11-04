<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\AppraisalCycle;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceConfig;
use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Certification;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\DriverIncident;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\EmploymentHistory;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\LeaveBalance;
use App\Modules\Hr\Models\LeavePolicy;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\PublicHoliday;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\Skill;
use App\Modules\Hr\Models\TimeClockEvent;
use App\Modules\Hr\Models\TrainingProgram;
use App\Modules\Hr\Models\TrainingSession;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Hr\Models\VehicleChecklist;
use App\Modules\Hr\Models\VehicleFuelRecord;
use App\Modules\Hr\Models\VehicleMaintenanceRecord;
use App\Modules\Hr\Models\WorkTeam;
use App\Modules\Hr\Models\WorkTeamMember;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        // ── 0. HR Permissions ─────────────────────────────────────────
        $permissions = [
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.edit',
            'hr.employees.archive',
            'hr.employees.view-sensitive',
            'hr.departments.view',
            'hr.departments.manage',
            'hr.positions.view',
            'hr.positions.manage',
            'hr.teams.view',
            'hr.teams.manage',
            'hr.attendance.view',
            'hr.attendance.manage',
            'hr.attendance.correct',
            'hr.attendance.kiosk',
            'hr.leave.view',
            'hr.leave.request',
            'hr.leave.approve',
            'hr.leave.manage',
            'hr.recruitment.view',
            'hr.recruitment.manage',
            'hr.recruitment.hire',
            'hr.training.view',
            'hr.training.manage',
            'hr.certifications.view',
            'hr.certifications.manage',
            'hr.skills.view',
            'hr.skills.manage',
            'hr.workforce.view',
            'hr.workforce.assign',
            'hr.appraisals.view',
            'hr.appraisals.manage',
            'hr.fleet.view',
            'hr.fleet.manage',
            'hr.fleet.archive',
            'read-hr-dashboard',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }

        // Standard HR Role Templates
        $hrRoleTemplates = [
            'HR Manager' => $permissions,
            'HR Officer' => [
                'read-PeopleSkills',
                'read-hr-dashboard',
                'hr.employees.view',
                'hr.employees.create',
                'hr.employees.edit',
                'hr.departments.view',
                'hr.positions.view',
                'hr.teams.view',
                'hr.attendance.view',
                'hr.leave.view',
                'hr.leave.request',
                'hr.recruitment.view',
                'hr.recruitment.manage',
                'hr.training.view',
                'hr.training.manage',
                'hr.certifications.view',
                'hr.certifications.manage',
                'hr.skills.view',
                'hr.skills.manage',
                'hr.workforce.view',
            ],
            'Attendance Manager' => [
                'hr.attendance.view',
                'hr.attendance.manage',
                'hr.attendance.correct',
                'hr.attendance.kiosk',
                'hr.workforce.view',
                'hr.workforce.assign',
                'hr.employees.view',
            ],
            'Leave Approver' => [
                'hr.leave.view',
                'hr.leave.approve',
                'hr.leave.request',
                'hr.employees.view',
                'hr.attendance.view',
            ],
            'Recruiter' => [
                'hr.recruitment.view',
                'hr.recruitment.manage',
                'hr.recruitment.hire',
                'hr.positions.view',
                'hr.departments.view',
                'hr.employees.view',
            ],
            'Fleet Manager' => [
                'hr.fleet.view',
                'hr.fleet.manage',
                'hr.fleet.archive',
                'hr.employees.view',
            ],
            'Employee Self-Service' => [
                'hr.leave.request',
            ],
        ];

        foreach ($hrRoleTemplates as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        $opsRole = Role::where('name', 'operations_responsible')->first();
        if ($opsRole) {
            $opsRole->givePermissionTo([
                'hr.employees.view',
                'hr.attendance.view',
                'hr.leave.view',
                'hr.workforce.view',
                'hr.workforce.assign',
                'hr.certifications.view',
                'hr.fleet.view',
            ]);
        }

        // ── 1. Departments ────────────────────────────────────────────
        $company = Department::updateOrCreate(['code' => 'HQ'], ['name' => $this->translations('WAVE Plus Headquarters'), 'sort_order' => 1]);
        $ops = Department::updateOrCreate(['code' => 'OPS'], ['name' => $this->translations('Operations'), 'parent_department_id' => $company->id, 'sort_order' => 2]);
        $intervention = Department::updateOrCreate(['code' => 'OPS-INT'], ['name' => $this->translations('Intervention & Maintenance'), 'parent_department_id' => $ops->id, 'sort_order' => 3]);
        $workshop = Department::updateOrCreate(['code' => 'WRK'], ['name' => $this->translations('Workshop & Technical Lab'), 'parent_department_id' => $company->id, 'sort_order' => 4]);
        $logistics = Department::updateOrCreate(['code' => 'LOG'], ['name' => $this->translations('Logistics & Fleet'), 'parent_department_id' => $company->id, 'sort_order' => 5]);
        $warehouse = Department::updateOrCreate(['code' => 'WHS'], ['name' => $this->translations('Warehouse & Inventory'), 'parent_department_id' => $company->id, 'sort_order' => 6]);
        $hrDept = Department::updateOrCreate(['code' => 'HRD'], ['name' => $this->translations('Human Resources & Talent'), 'parent_department_id' => $company->id, 'sort_order' => 7]);
        $commercial = Department::updateOrCreate(['code' => 'COM'], ['name' => $this->translations('Commercial & Sales'), 'parent_department_id' => $company->id, 'sort_order' => 8]);

        // ── 2. Positions ──────────────────────────────────────────────
        $posOpsMgr = Position::updateOrCreate(['code' => 'POS-OM'], ['title' => $this->translations('Operations Manager'), 'department_id' => $ops->id]);
        $posTeamLead = Position::updateOrCreate(['code' => 'POS-TL'], ['title' => $this->translations('Team Leader'), 'department_id' => $intervention->id]);
        $posFireTech = Position::updateOrCreate(['code' => 'POS-FST'], ['title' => $this->translations('Fire Safety Technician'), 'department_id' => $intervention->id]);
        $posWrkTech = Position::updateOrCreate(['code' => 'POS-WT'], ['title' => $this->translations('Workshop Technician'), 'department_id' => $workshop->id]);
        $posDriver = Position::updateOrCreate(['code' => 'POS-DRV'], ['title' => $this->translations('Fleet Logistics Driver'), 'department_id' => $logistics->id]);
        $posWhsOp = Position::updateOrCreate(['code' => 'POS-WHO'], ['title' => $this->translations('Warehouse Operator'), 'department_id' => $warehouse->id]);
        $posHrMgr = Position::updateOrCreate(['code' => 'POS-HRM'], ['title' => $this->translations('HR & People Manager'), 'department_id' => $hrDept->id]);
        $posSales = Position::updateOrCreate(['code' => 'POS-SALES'], ['title' => $this->translations('Sales Coordinator'), 'department_id' => $commercial->id]);
        $posElectrician = Position::updateOrCreate(['code' => 'POS-ELEC'], ['title' => $this->translations('Electrical Maintenance Technician'), 'department_id' => $workshop->id]);
        $posHse = Position::updateOrCreate(['code' => 'POS-HSE'], ['title' => $this->translations('HSE Coordinator'), 'department_id' => $intervention->id]);

        // ── 3. Work Teams ─────────────────────────────────────────────
        $teamA = WorkTeam::updateOrCreate(['code' => 'TEAM-A'], ['name' => $this->translations('Team Alpha (Intervention)'), 'department_id' => $intervention->id, 'team_type' => 'Intervention', 'is_active' => true]);
        $teamB = WorkTeam::updateOrCreate(['code' => 'TEAM-B'], ['name' => $this->translations('Team Bravo (Maintenance)'), 'department_id' => $intervention->id, 'team_type' => 'Maintenance', 'is_active' => true]);
        $teamEmergency = WorkTeam::updateOrCreate(['code' => 'TEAM-EMG'], ['name' => $this->translations('Emergency Response Team'), 'department_id' => $intervention->id, 'team_type' => 'Emergency', 'is_active' => true]);
        $teamWorkshop = WorkTeam::updateOrCreate(['code' => 'TEAM-WRK'], ['name' => $this->translations('Workshop Crew'), 'department_id' => $workshop->id, 'team_type' => 'Workshop', 'is_active' => true]);
        $teamLogistics = WorkTeam::updateOrCreate(['code' => 'TEAM-LOG'], ['name' => $this->translations('Logistics Drivers'), 'department_id' => $logistics->id, 'team_type' => 'Logistics', 'is_active' => true]);

        // ── 4. Skills ─────────────────────────────────────────────────
        $skillFireExt = Skill::updateOrCreate(['code' => 'SK-EXT'], ['name' => $this->translations('Fire Extinguisher Maintenance'), 'category' => 'Fire Safety', 'is_active' => true]);
        $skillRia = Skill::updateOrCreate(['code' => 'SK-RIA'], ['name' => $this->translations('RIA & Hose Reels Maintenance'), 'category' => 'Fire Safety', 'is_active' => true]);
        $skillHydrant = Skill::updateOrCreate(['code' => 'SK-HYD'], ['name' => $this->translations('Hydrant System Servicing'), 'category' => 'Fire Safety', 'is_active' => true]);
        $skillPump = Skill::updateOrCreate(['code' => 'SK-PUMP'], ['name' => $this->translations('Fire Pump Operations & Testing'), 'category' => 'Mechanical', 'is_active' => true]);
        $skillAlarm = Skill::updateOrCreate(['code' => 'SK-ALM'], ['name' => $this->translations('Fire Detection & Alarm Systems'), 'category' => 'Electrical', 'is_active' => true]);
        $skillElec = Skill::updateOrCreate(['code' => 'SK-ELEC'], ['name' => $this->translations('Electrical Maintenance & Diagnostic'), 'category' => 'Electrical', 'is_active' => true]);
        $skillHyd = Skill::updateOrCreate(['code' => 'SK-HYDL'], ['name' => $this->translations('Hydraulic Overhaul'), 'category' => 'Mechanical', 'is_active' => true]);
        $skillWeld = Skill::updateOrCreate(['code' => 'SK-WELD'], ['name' => $this->translations('Industrial Welding'), 'category' => 'Workshop', 'is_active' => true]);
        $skillDrive = Skill::updateOrCreate(['code' => 'SK-DRV'], ['name' => $this->translations('Heavy & Commercial Driving'), 'category' => 'Logistics', 'is_active' => true]);
        $skillHeight = Skill::updateOrCreate(['code' => 'SK-HGT'], ['name' => $this->translations('Working at Height'), 'category' => 'Safety', 'is_active' => true]);
        $skillConfined = Skill::updateOrCreate(['code' => 'SK-CONF'], ['name' => $this->translations('Confined Space Intervention'), 'category' => 'Safety', 'is_active' => true]);
        $skillFireSafety = Skill::updateOrCreate(['code' => 'SK-FIRE'], ['name' => $this->translations('Fire Safety'), 'category' => 'Fire Safety', 'is_active' => true]);
        $skillHse = Skill::updateOrCreate(['code' => 'SK-HSE'], ['name' => $this->translations('Health, Safety & Environment'), 'category' => 'Safety', 'is_active' => true]);
        $skillCustomerService = Skill::updateOrCreate(['code' => 'SK-CUST'], ['name' => $this->translations('Customer Service'), 'category' => 'Business', 'is_active' => true]);
        $skillTeamManagement = Skill::updateOrCreate(['code' => 'SK-TEAM'], ['name' => $this->translations('Team Management'), 'category' => 'Management', 'is_active' => true]);
        $skillFirstAid = Skill::updateOrCreate(['code' => 'SK-AID'], ['name' => $this->translations('First Aid'), 'category' => 'Safety', 'is_active' => true]);
        $skillInspection = Skill::updateOrCreate(['code' => 'SK-INSP'], ['name' => $this->translations('Technical Inspection'), 'category' => 'Technical', 'is_active' => true]);

        // ── 5. Certifications ─────────────────────────────────────────
        $certDriver = Certification::updateOrCreate(['code' => 'CERT-DL-C'], ['name' => $this->translations('Professional Driving License (Class C)'), 'issuing_authority' => $this->translations('Ministry of Transport'), 'validity_period_months' => 60]);
        $certFirstAid = Certification::updateOrCreate(['code' => 'CERT-SST'], ['name' => $this->translations('First Aid at Work (SST / Sauveteur Secouriste)'), 'issuing_authority' => $this->translations('National Safety Institute'), 'validity_period_months' => 24]);
        $certHeight = Certification::updateOrCreate(['code' => 'CERT-HGT'], ['name' => $this->translations('Working at Height Authorization'), 'issuing_authority' => $this->translations('Bureau Veritas'), 'validity_period_months' => 36]);
        $certSsiap = Certification::updateOrCreate(['code' => 'CERT-SSIAP'], ['name' => $this->translations('Fire Safety Qualification (SSIAP 1/2)'), 'issuing_authority' => $this->translations('Civil Protection Board'), 'validity_period_months' => 36]);
        $certElec = Certification::updateOrCreate(['code' => 'CERT-ELEC-BR'], ['name' => $this->translations('Electrical Certification (BR / B2V)'), 'issuing_authority' => $this->translations('Apave Safety'), 'validity_period_months' => 36]);
        $certForklift = Certification::updateOrCreate(['code' => 'CERT-CACES'], ['name' => $this->translations('Forklift Operator Certificate (CACES R489)'), 'issuing_authority' => $this->translations('CACES Board'), 'validity_period_months' => 60]);

        // ── 6. Shifts & Working Regimes (Tunisian Labor Context) ─────
        // 48h Regime: standard industrial/services week (6 days × 8h = 48h, e.g. Mon-Fri 08:00-17:00, Sat 08:00-16:00)
        // 40h Regime: administrative office week (5 days × 8h = 40h)
        // Summer & Ramadan: "Séance unique"
        $shift48Reg = Shift::updateOrCreate(['code' => '48H-REG'], [
            'name' => $this->translations('Standard 48-Hour Regime Shift'),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_duration_minutes' => 60,
            'is_overnight' => false,
            'is_active' => true,
        ]);

        $shift40Adm = Shift::updateOrCreate(['code' => '40H-ADM'], [
            'name' => $this->translations('Administrative 40-Hour Regime Shift'),
            'start_time' => '08:30:00',
            'end_time' => '17:30:00',
            'break_duration_minutes' => 60,
            'is_overnight' => false,
            'is_active' => true,
        ]);

        $shiftWinter = Shift::updateOrCreate(['code' => 'WINTER'], [
            'name' => $this->translations('Winter Shift'),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_duration_minutes' => 60,
            'is_overnight' => false,
            'is_active' => true,
        ]);

        $shiftSummer = Shift::updateOrCreate(['code' => 'SUMMER'], [
            'name' => $this->translations('Summer Shift'),
            'start_time' => '08:00:00',
            'end_time' => '14:30:00',
            'break_duration_minutes' => 30,
            'is_overnight' => false,
            'is_active' => true,
        ]);

        $shiftSaturday = Shift::updateOrCreate(['code' => 'SATURDAY'], [
            'name' => $this->translations('Saturday Shift'),
            'start_time' => '08:00:00',
            'end_time' => '13:30:00',
            'break_duration_minutes' => 0,
            'is_overnight' => false,
            'is_active' => true,
        ]);

        $shiftRamadan = Shift::updateOrCreate(['code' => 'RAMADAN'], [
            'name' => $this->translations('Ramadan Shift'),
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'break_duration_minutes' => 0,
            'is_overnight' => false,
            'is_active' => true,
        ]);

        $shiftAstreinte = Shift::updateOrCreate(['code' => 'GARDE-24'], [
            'name' => $this->translations('24h On-Call Emergency Guard'),
            'start_time' => '08:00:00',
            'end_time' => '08:00:00',
            'break_duration_minutes' => 120,
            'is_overnight' => true,
            'is_active' => true,
        ]);

        // ── 7. Tunisian Statutory Public Holidays 2026 ────────────────
        $tunisianHolidays2026 = [
            ['name' => 'Jour de l’An / رأس السنة الميلادية', 'date' => '2026-01-01', 'recurring' => true],
            ['name' => 'Fête de l’Indépendance / عيد الاستقلال', 'date' => '2026-03-20', 'recurring' => true],
            ['name' => 'Aïd El-Fitr (Jour 1) / عيد الفطر', 'date' => '2026-03-21', 'recurring' => false],
            ['name' => 'Aïd El-Fitr (Jour 2) / عيد الفطر', 'date' => '2026-03-22', 'recurring' => false],
            ['name' => 'Fête des Martyrs / عيد الشهداء', 'date' => '2026-04-09', 'recurring' => true],
            ['name' => 'Fête du Travail / عيد الشغل', 'date' => '2026-05-01', 'recurring' => true],
            ['name' => 'Aïd El-Idha (Jour 1) / عيد الأضحى', 'date' => '2026-05-27', 'recurring' => false],
            ['name' => 'Aïd El-Idha (Jour 2) / عيد الأضحى', 'date' => '2026-05-28', 'recurring' => false],
            ['name' => 'Nouvel An Hégirien 1448 / رأس السنة الهجرية', 'date' => '2026-06-17', 'recurring' => false],
            ['name' => 'Fête de la République / عيد الجمهورية', 'date' => '2026-07-25', 'recurring' => true],
            ['name' => 'Fête Nationale de la Femme / عيد المرأة', 'date' => '2026-08-13', 'recurring' => true],
            ['name' => 'Le Mouled (Naissance du Prophète) / المولد النبوي', 'date' => '2026-08-26', 'recurring' => false],
            ['name' => 'Fête de l’Évacuation / عيد الجلاء', 'date' => '2026-10-15', 'recurring' => true],
            ['name' => 'Fête de la Révolution / عيد الثورة', 'date' => '2026-12-17', 'recurring' => true],
        ];

        foreach ($tunisianHolidays2026 as $h) {
            PublicHoliday::updateOrCreate(
                ['date' => $h['date'], 'country_code' => 'TN'],
                [
                    'name' => [
                        'en' => $h['name'],
                        'fr' => $h['name'],
                        'ar' => $h['name'],
                    ],
                    'year' => 2026,
                    'is_recurring' => $h['recurring'],
                    'is_half_day' => false,
                    'applies_to_all' => true,
                    'is_active' => true,
                    'notes' => 'Tunisian statutory public holiday under Code du Travail',
                ]
            );
        }

        // ── 8. Attendance Configuration ───────────────────────────────
        AttendanceConfig::updateOrCreate(
            ['scope' => 'global', 'scope_id' => null],
            [
                'early_checkin_grace_minutes' => 15,
                'late_checkin_grace_minutes' => 15,
                'early_checkout_grace_minutes' => 15,
                'late_checkout_grace_minutes' => 15,
                'hourly_leave_tolerance_minutes' => 15,
                'min_hourly_leave_unit_minutes' => 15,
            ]
        );

        // ── 9. Leave Types & Policies (Tunisian Labor Code) ───────────
        // Art. 112+ Code du Travail: 1.75 days per month of actual work = 21 working days per year
        $leaveAnnual = LeaveType::updateOrCreate(['code' => 'LT-ANN'], ['name' => $this->translations('Paid Annual Leave'), 'max_days_per_year' => 21.0, 'is_paid' => true]);
        $leaveSick = LeaveType::updateOrCreate(['code' => 'LT-SCK'], ['name' => $this->translations('Sick Leave'), 'max_days_per_year' => 15.0, 'is_paid' => true]);
        $leaveExcept = LeaveType::updateOrCreate(['code' => 'LT-EXC'], ['name' => $this->translations('Exceptional Family Leave'), 'max_days_per_year' => 5.0, 'is_paid' => true]);
        $leaveMaternity = LeaveType::updateOrCreate(['code' => 'LT-MAT'], ['name' => $this->translations('Maternity Leave'), 'max_days_per_year' => 30.0, 'is_paid' => true]);
        $leavePaternity = LeaveType::updateOrCreate(['code' => 'LT-PAT'], ['name' => $this->translations('Paternity Leave'), 'max_days_per_year' => 2.0, 'is_paid' => true]);
        $leaveUnpaid = LeaveType::updateOrCreate(['code' => 'LT-UNP'], ['name' => $this->translations('Unpaid Leave'), 'max_days_per_year' => 30.0, 'is_paid' => false]);
        $leaveTrain = LeaveType::updateOrCreate(['code' => 'LT-TRN'], ['name' => $this->translations('Training & Development Leave'), 'max_days_per_year' => 10.0, 'is_paid' => true]);

        // Leave policies: 1.75 days per month accrual
        foreach ([$ops, $intervention, $workshop, $logistics, $warehouse, $hrDept, $commercial] as $dept) {
            LeavePolicy::updateOrCreate(
                ['leave_type_id' => $leaveAnnual->id, 'department_id' => $dept->id],
                ['accrual_rate_per_month' => 1.75, 'max_carryover_days' => 6.0]
            );
            LeavePolicy::updateOrCreate(
                ['leave_type_id' => $leaveSick->id, 'department_id' => $dept->id],
                ['accrual_rate_per_month' => 1.25, 'max_carryover_days' => 0.0]
            );
        }

        // ── 10. Training Programs ─────────────────────────────────────
        $tpFireSafety = TrainingProgram::updateOrCreate(['code' => 'TR-FS-01'], [
            'name' => $this->translations('Advanced Fire Extinguisher & RIA Servicing'),
            'category' => 'Fire Safety',
            'provider' => $this->translations('WAVE Academy & Safety Training'),
            'duration_hours' => 16.0,
            'target_skill_id' => $skillFireExt->id,
            'target_certification_id' => $certSsiap->id,
        ]);

        $tpHeight = TrainingProgram::updateOrCreate(['code' => 'TR-HGT-01'], [
            'name' => $this->translations('Fall Protection & Working at Height Certification'),
            'category' => 'Safety',
            'provider' => $this->translations('Apave Industrial Safety'),
            'duration_hours' => 8.0,
            'target_skill_id' => $skillHeight->id,
            'target_certification_id' => $certHeight->id,
        ]);

        $session1 = TrainingSession::updateOrCreate(['session_code' => 'SESS-2026-01'], [
            'training_program_id' => $tpFireSafety->id,
            'trainer' => $this->translations('Mohamed Ben Ali (Master Instructor)'),
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'location' => 'Tunis Technical Training Center (Charguia 2)',
            'max_participants' => 12,
            'status' => 'scheduled',
        ]);

        // ── 11. Appraisal Cycle ───────────────────────────────────────
        $cycle2026 = AppraisalCycle::firstOrCreate(['name' => '2026 Annual Performance & Growth Review'], [
            'cycle_type' => 'annual',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        // ── 12. Tunisian Employees (Deterministic numbers EMP-00001 to EMP-00012) ──
        $demoEmployeesData = [
            [
                'number' => 'EMP-00001',
                'first_name' => 'Ahmed',
                'last_name' => 'Mansouri',
                'gender' => 'male',
                'date_of_birth' => '1982-04-12',
                'work_email' => 'ahmed.mansouri@waveplus.io',
                'personal_email' => 'ahmed.mansouri.demo@example.tn',
                'work_phone' => '+216 71 200 301',
                'hire_date' => '2023-03-15',
                'status' => 'active',
                'department_id' => $ops->id,
                'position_id' => $posOpsMgr->id,
                'current_team_id' => null,
                'manager_email' => null,
                'contract_type' => 'permanent',
                'address' => 'Résidence Les Palmiers, Les Berges du Lac 2, 1053 Tunis',
                'skills' => ['SK-TEAM' => 'advanced', 'SK-CUST' => 'advanced'],
            ],
            [
                'number' => 'EMP-00002',
                'first_name' => 'Sami',
                'last_name' => 'Ben Salah',
                'gender' => 'male',
                'date_of_birth' => '1988-11-03',
                'work_email' => 'sami.bensalah@waveplus.io',
                'personal_email' => 'sami.bensalah.demo@example.tn',
                'work_phone' => '+216 71 200 302',
                'hire_date' => '2024-01-10',
                'status' => 'active',
                'department_id' => $intervention->id,
                'position_id' => $posTeamLead->id,
                'current_team_id' => $teamA->id,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'permanent',
                'address' => '14 Rue Ibn Khaldoun, Ennasr 2, 2037 Ariana',
                'skills' => ['SK-EXT' => 'expert', 'SK-RIA' => 'advanced', 'SK-FIRE' => 'advanced', 'SK-HSE' => 'intermediate', 'SK-TEAM' => 'advanced'],
            ],
            [
                'number' => 'EMP-00003',
                'first_name' => 'Karim',
                'last_name' => 'Gharbi',
                'gender' => 'male',
                'date_of_birth' => '1991-02-21',
                'work_email' => 'karim.gharbi@waveplus.io',
                'personal_email' => 'karim.gharbi.demo@example.tn',
                'work_phone' => '+216 71 200 303',
                'hire_date' => '2024-05-20',
                'status' => 'active',
                'department_id' => $intervention->id,
                'position_id' => $posFireTech->id,
                'current_team_id' => $teamA->id,
                'manager_email' => 'sami.bensalah@waveplus.io',
                'contract_type' => 'fixed_term',
                'address' => '28 Avenue Habib Bourguiba, Megrine, 2033 Ben Arous',
                'skills' => ['SK-EXT' => 'intermediate', 'SK-ALM' => 'intermediate', 'SK-INSP' => 'intermediate', 'SK-AID' => 'basic'],
            ],
            [
                'number' => 'EMP-00004',
                'first_name' => 'Yassine',
                'last_name' => 'Trabelsi',
                'gender' => 'male',
                'date_of_birth' => '1993-07-08',
                'work_email' => 'yassine.trabelsi@waveplus.io',
                'personal_email' => 'yassine.trabelsi.demo@example.tn',
                'work_phone' => '+216 71 200 304',
                'hire_date' => '2024-08-01',
                'status' => 'active',
                'department_id' => $intervention->id,
                'position_id' => $posFireTech->id,
                'current_team_id' => $teamB->id,
                'manager_email' => 'sami.bensalah@waveplus.io',
                'contract_type' => 'permanent',
                'address' => '5 Rue de la Liberté, Ezzahra, 2034 Ben Arous',
                'skills' => ['SK-EXT' => 'advanced', 'SK-HGT' => 'intermediate', 'SK-CONF' => 'intermediate', 'SK-HSE' => 'basic'],
            ],
            [
                'number' => 'EMP-00005',
                'first_name' => 'Nour',
                'last_name' => 'Hammami',
                'gender' => 'female',
                'date_of_birth' => '1990-05-16',
                'work_email' => 'nour.hammami@waveplus.io',
                'personal_email' => 'nour.hammami.demo@example.tn',
                'work_phone' => '+216 71 200 305',
                'hire_date' => '2023-11-01',
                'status' => 'active',
                'department_id' => $workshop->id,
                'position_id' => $posWrkTech->id,
                'current_team_id' => $teamWorkshop->id,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'permanent',
                'address' => '32 Avenue de France, Manouba, 2010 Manouba',
                'skills' => ['SK-ELEC' => 'advanced', 'SK-WELD' => 'intermediate', 'SK-INSP' => 'advanced'],
            ],
            [
                'number' => 'EMP-00006',
                'first_name' => 'Bilel',
                'last_name' => 'Kefi',
                'gender' => 'male',
                'date_of_birth' => '1987-09-25',
                'work_email' => 'bilel.kefi@waveplus.io',
                'personal_email' => 'bilel.kefi.demo@example.tn',
                'work_phone' => '+216 71 200 306',
                'hire_date' => '2024-02-15',
                'status' => 'active',
                'department_id' => $logistics->id,
                'position_id' => $posDriver->id,
                'current_team_id' => $teamLogistics->id,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'permanent',
                'address' => '18 Rue Taieb Mhiri, Radès, 2040 Ben Arous',
                'skills' => ['SK-DRV' => 'expert', 'SK-CUST' => 'intermediate', 'SK-AID' => 'basic'],
            ],
            [
                'number' => 'EMP-00007',
                'first_name' => 'Meriem',
                'last_name' => 'Chaabane',
                'gender' => 'female',
                'date_of_birth' => '1995-01-30',
                'work_email' => 'meriem.chaabane@waveplus.io',
                'personal_email' => 'meriem.chaabane.demo@example.tn',
                'work_phone' => '+216 71 200 307',
                'hire_date' => '2025-02-03',
                'status' => 'active',
                'department_id' => $warehouse->id,
                'position_id' => $posWhsOp->id,
                'current_team_id' => null,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'probation',
                'address' => '4 Rue Farhat Hached, Ariana Ville, 2080 Ariana',
                'skills' => ['SK-INSP' => 'basic', 'SK-HSE' => 'basic'],
            ],
            [
                'number' => 'EMP-00008',
                'first_name' => 'Rania',
                'last_name' => 'Jaziri',
                'gender' => 'female',
                'date_of_birth' => '1985-12-19',
                'work_email' => 'rania.jaziri@waveplus.io',
                'personal_email' => 'rania.jaziri.demo@example.tn',
                'work_phone' => '+216 71 200 308',
                'hire_date' => '2022-06-01',
                'status' => 'active',
                'department_id' => $hrDept->id,
                'position_id' => $posHrMgr->id,
                'current_team_id' => null,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'permanent',
                'address' => '9 Rue Mongi Slim, Mutuelleville, 1082 Tunis',
                'skills' => ['SK-TEAM' => 'advanced', 'SK-CUST' => 'advanced', 'SK-AID' => 'intermediate'],
            ],
            [
                'number' => 'EMP-00009',
                'first_name' => 'Fares',
                'last_name' => 'Ben Youssef',
                'gender' => 'male',
                'date_of_birth' => '1992-03-14',
                'work_email' => 'fares.benyoussef@waveplus.io',
                'personal_email' => 'fares.benyoussef.demo@example.tn',
                'work_phone' => '+216 71 200 309',
                'hire_date' => '2024-09-09',
                'status' => 'active',
                'department_id' => $commercial->id,
                'position_id' => $posSales->id,
                'current_team_id' => null,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'fixed_term',
                'address' => '21 Rue de Palestine, Lafayette, 1002 Tunis',
                'skills' => ['SK-CUST' => 'advanced', 'SK-FIRE' => 'basic'],
            ],
            [
                'number' => 'EMP-00010',
                'first_name' => 'Hichem',
                'last_name' => 'Saidi',
                'gender' => 'male',
                'date_of_birth' => '1989-06-27',
                'work_email' => 'hichem.saidi@waveplus.io',
                'personal_email' => 'hichem.saidi.demo@example.tn',
                'work_phone' => '+216 71 200 310',
                'hire_date' => '2023-07-17',
                'status' => 'active',
                'department_id' => $workshop->id,
                'position_id' => $posElectrician->id,
                'current_team_id' => $teamWorkshop->id,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'permanent',
                'address' => '12 Rue Hédi Nouira, Ariana, 2037 Ariana',
                'skills' => ['SK-ELEC' => 'expert', 'SK-ALM' => 'advanced', 'SK-INSP' => 'advanced'],
            ],
            [
                'number' => 'EMP-00011',
                'first_name' => 'Amira',
                'last_name' => 'Bouazizi',
                'gender' => 'female',
                'date_of_birth' => '1994-10-05',
                'work_email' => 'amira.bouazizi@waveplus.io',
                'personal_email' => 'amira.bouazizi.demo@example.tn',
                'work_phone' => '+216 71 200 311',
                'hire_date' => '2024-04-22',
                'status' => 'on_leave',
                'department_id' => $intervention->id,
                'position_id' => $posHse->id,
                'current_team_id' => $teamEmergency->id,
                'manager_email' => 'sami.bensalah@waveplus.io',
                'contract_type' => 'permanent',
                'address' => '8 Rue de Marseille, Tunis Centre, 1000 Tunis',
                'skills' => ['SK-HSE' => 'advanced', 'SK-AID' => 'advanced', 'SK-HGT' => 'basic'],
            ],
            [
                'number' => 'EMP-00012',
                'first_name' => 'Walid',
                'last_name' => 'Kacem',
                'gender' => 'male',
                'date_of_birth' => '1997-08-11',
                'work_email' => 'walid.kacem@waveplus.io',
                'personal_email' => 'walid.kacem.demo@example.tn',
                'work_phone' => '+216 71 200 312',
                'hire_date' => '2025-05-12',
                'status' => 'active',
                'department_id' => $warehouse->id,
                'position_id' => $posWhsOp->id,
                'current_team_id' => null,
                'manager_email' => 'ahmed.mansouri@waveplus.io',
                'contract_type' => 'temporary',
                'address' => '45 Avenue de la République, La Goulette, 2060 Tunis',
                'skills' => ['SK-INSP' => 'basic', 'SK-HSE' => 'basic'],
            ],
        ];

        $createdEmployees = [];
        $employeesByEmail = [];
        $employeesByNumber = [];

        foreach ($demoEmployeesData as $empData) {
            $user = User::firstOrCreate(['email' => $empData['work_email']], [
                'name' => $empData['first_name'] . ' ' . $empData['last_name'],
                'first_name' => $empData['first_name'],
                'last_name' => $empData['last_name'],
                'password' => bcrypt('wave-demo-' . substr(hash('sha256', $empData['work_email']), 0, 24)),
                'user_type' => 'employee',
                'date_of_birth' => $empData['date_of_birth'],
                'gender' => $empData['gender'],
                'is_active' => true,
                'can_login' => true,
                'account_status' => 'active',
            ]);

            $employee = Employee::updateOrCreate(
                ['work_email' => $empData['work_email']],
                [
                    'employee_number' => $empData['number'],
                    'user_id' => $user->id,
                    'first_name' => $this->translations($empData['first_name']),
                    'last_name' => $this->translations($empData['last_name']),
                    'gender' => $empData['gender'],
                    'date_of_birth' => $empData['date_of_birth'],
                    'nationality' => 'Tunisian',
                    'personal_email' => $empData['personal_email'],
                    'work_phone' => $empData['work_phone'],
                    'personal_phone' => $empData['work_phone'],
                    'address' => $empData['address'],
                    'hire_date' => $empData['hire_date'],
                    'status' => $empData['status'],
                    'department_id' => $empData['department_id'],
                    'position_id' => $empData['position_id'],
                    'current_team_id' => $empData['current_team_id'],
                ]
            );

            $employeesByEmail[$empData['work_email']] = $employee;
            $employeesByNumber[$empData['number']] = $employee;
            $createdEmployees[] = $employee;

            EmploymentHistory::firstOrCreate(
                ['employee_id' => $employee->id, 'start_date' => $employee->hire_date],
                [
                    'position_id' => $employee->position_id,
                    'department_id' => $employee->department_id,
                    'team_id' => $employee->current_team_id,
                    'employment_type' => 'full_time',
                    'contract_type' => $empData['contract_type'],
                    'reason_for_change' => 'Initial hire',
                ]
            );

            if ($employee->current_team_id) {
                WorkTeamMember::firstOrCreate(
                    ['team_id' => $employee->current_team_id, 'employee_id' => $employee->id],
                    ['is_primary' => true, 'joined_at' => $employee->hire_date]
                );
            }

            // Tunisian standard leave balances (21 days annual leave)
            LeaveBalance::updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveAnnual->id, 'year' => 2026],
                ['accrued_days' => 21.0, 'approved_used_days' => 3.0, 'pending_reserved_days' => 0.0, 'available_days' => 18.0]
            );

            LeaveBalance::updateOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $leaveSick->id, 'year' => 2026],
                ['accrued_days' => 15.0, 'approved_used_days' => 1.0, 'pending_reserved_days' => 0.0, 'available_days' => 14.0]
            );

            foreach ($empData['skills'] as $skillCode => $level) {
                $skill = Skill::where('code', $skillCode)->first();
                if ($skill) {
                    EmployeeSkill::updateOrCreate(
                        ['employee_id' => $employee->id, 'skill_id' => $skill->id],
                        ['level' => $level, 'is_verified' => true, 'acquired_date' => $employee->hire_date]
                    );
                }
            }
        }

        // Managers linking
        foreach ($demoEmployeesData as $empData) {
            $employee = $employeesByEmail[$empData['work_email']];
            if ($empData['manager_email'] && isset($employeesByEmail[$empData['manager_email']])) {
                $employee->update(['manager_id' => $employeesByEmail[$empData['manager_email']]->id]);
            }
        }

        $ops->update(['manager_id' => $employeesByEmail['ahmed.mansouri@waveplus.io']->id]);
        $intervention->update(['manager_id' => $employeesByEmail['sami.bensalah@waveplus.io']->id]);
        $teamA->update(['team_leader_id' => $employeesByEmail['sami.bensalah@waveplus.io']->id]);

        // Synchronize EMP document sequence past the seeded employees
        DB::table('document_sequences')->updateOrInsert(
            ['document_type' => 'EMP'],
            ['id' => (string) Str::ulid(), 'current_value' => 50, 'updated_at' => now(), 'created_at' => now()]
        );

        // ── 13. Weekly Schedules (Tunisian 48h Regime & Shifts) ───────
        $currentMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $nextMonday = $currentMonday->copy()->addWeek();

        $scheduleEmployees = [
            $employeesByEmail['sami.bensalah@waveplus.io'],
            $employeesByEmail['karim.gharbi@waveplus.io'],
            $employeesByEmail['yassine.trabelsi@waveplus.io'],
            $employeesByEmail['nour.hammami@waveplus.io'],
            $employeesByEmail['bilel.kefi@waveplus.io'],
            $employeesByEmail['hichem.saidi@waveplus.io'],
        ];

        foreach ([$currentMonday, $nextMonday] as $weekStart) {
            foreach ($scheduleEmployees as $emp) {
                for ($day = 0; $day < 6; $day++) {
                    $date = $weekStart->copy()->addDays($day)->toDateString();
                    $targetShift = ($day === 5) ? $shiftSaturday : $shift48Reg;

                    Schedule::updateOrCreate(
                        ['employee_id' => $emp->id, 'date' => $date],
                        [
                            'shift_id' => $targetShift->id,
                            'status' => 'scheduled',
                            'lifecycle_status' => Schedule::STATUS_PLANNED,
                            'coverage_status' => Schedule::COVERAGE_NORMAL,
                            'notes' => 'Régime 48h légal tunisien (Lun-Sam)',
                        ]
                    );
                }
            }
        }

        // ── 14. Certifications for Technicians ────────────────────────
        $sami = $employeesByEmail['sami.bensalah@waveplus.io'];
        EmployeeCertification::updateOrCreate(
            ['employee_id' => $sami->id, 'certification_id' => $certSsiap->id],
            [
                'certificate_number' => 'SSIAP-TN-2024-8891',
                'issue_date' => '2024-02-01',
                'expiry_date' => now()->addMonths(18)->toDateString(),
                'status' => 'valid',
            ]
        );

        $karim = $employeesByEmail['karim.gharbi@waveplus.io'];
        EmployeeCertification::updateOrCreate(
            ['employee_id' => $karim->id, 'certification_id' => $certHeight->id],
            [
                'certificate_number' => 'HGT-TN-2023-1122',
                'issue_date' => '2023-01-15',
                'expiry_date' => now()->subDays(10)->toDateString(),
                'status' => 'expired',
            ]
        );

        $yassine = $employeesByEmail['yassine.trabelsi@waveplus.io'];
        EmployeeCertification::updateOrCreate(
            ['employee_id' => $yassine->id, 'certification_id' => $certHeight->id],
            [
                'certificate_number' => 'HGT-TN-2025-4421',
                'issue_date' => '2025-01-10',
                'expiry_date' => now()->addDays(20)->toDateString(),
                'status' => 'expiring',
            ]
        );

        // ── 15. Sample Leave Requests ─────────────────────────────────
        $adminUser = User::first();
        LeaveRequest::updateOrCreate(
            ['reference' => 'LV-2026-0001'],
            [
                'employee_id' => $sami->id,
                'leave_type_id' => $leaveAnnual->id,
                'start_date' => now()->addDays(12)->toDateString(),
                'end_date' => now()->addDays(15)->toDateString(),
                'duration_type' => 'full_day',
                'total_days' => 4.0,
                'reason' => 'Congé annuel de repos familial',
                'status' => 'approved',
                'requested_by_id' => $sami->user_id,
                'approver_id' => $adminUser?->id,
                'approved_at' => now()->subDay(),
            ]
        );

        LeaveRequest::updateOrCreate(
            ['reference' => 'LV-2026-0002'],
            [
                'employee_id' => $karim->id,
                'leave_type_id' => $leaveExcept->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
                'duration_type' => 'full_day',
                'total_days' => 3.0,
                'reason' => 'Mariage familial (Art. 116 Code du Travail)',
                'status' => 'submitted',
                'requested_by_id' => $karim->user_id,
            ]
        );

        // ── 16. Today Attendances & Punches for Kiosk Demo ─────────────
        $today = now()->toDateString();

        // Sami Ben Salah: Present & punched in
        $attSami = Attendance::updateOrCreate(
            ['employee_id' => $sami->id, 'date' => $today],
            [
                'scheduled_in' => '08:00:00',
                'scheduled_out' => '17:00:00',
                'actual_in' => '07:55:00',
                'actual_out' => null,
                'scheduled_hours' => 8.0,
                'worked_hours' => 5.2,
                'late_minutes' => 0,
                'status' => 'present',
            ]
        );

        TimeClockEvent::updateOrCreate(
            ['employee_id' => $sami->id, 'event_time' => now()->startOfDay()->addHours(7)->addMinutes(55)],
            [
                'event_type' => 'in',
                'source' => 'qr',
                'device_id' => 'kiosk-terminal',
            ]
        );

        // Karim Gharbi: Late arrival punch (23 min late) -> creates Attendance Exception!
        $attKarim = Attendance::updateOrCreate(
            ['employee_id' => $karim->id, 'date' => $today],
            [
                'scheduled_in' => '08:00:00',
                'scheduled_out' => '17:00:00',
                'actual_in' => '08:23:00',
                'actual_out' => null,
                'scheduled_hours' => 8.0,
                'worked_hours' => 4.6,
                'late_minutes' => 23,
                'status' => 'late',
            ]
        );

        $punchKarim = TimeClockEvent::updateOrCreate(
            ['employee_id' => $karim->id, 'event_time' => now()->startOfDay()->addHours(8)->addMinutes(23)],
            [
                'event_type' => 'in',
                'source' => 'pin',
                'device_id' => 'kiosk-terminal',
            ]
        );

        AttendanceException::updateOrCreate(
            ['employee_id' => $karim->id, 'date' => $today, 'exception_type' => 'late_arrival'],
            [
                'attendance_id' => $attKarim->id,
                'time_clock_event_id' => $punchKarim->id,
                'expected_time' => '08:00:00',
                'actual_time' => '08:23:00',
                'deviation_minutes' => 23,
                'review_status' => 'open',
                'notes' => 'Retard de 23 minutes au pointage borne Kiosk (Embouteillage Tunis Sud)',
            ]
        );

        // Bilel Kefi (Logistics Driver): Present and punched in early
        $bilel = $employeesByEmail['bilel.kefi@waveplus.io'];
        Attendance::updateOrCreate(
            ['employee_id' => $bilel->id, 'date' => $today],
            [
                'scheduled_in' => '08:00:00',
                'scheduled_out' => '17:00:00',
                'actual_in' => '07:48:00',
                'actual_out' => null,
                'scheduled_hours' => 8.0,
                'worked_hours' => 5.4,
                'late_minutes' => 0,
                'status' => 'present',
            ]
        );

        TimeClockEvent::updateOrCreate(
            ['employee_id' => $bilel->id, 'event_time' => now()->startOfDay()->addHours(7)->addMinutes(48)],
            [
                'event_type' => 'in',
                'source' => 'qr',
                'device_id' => 'kiosk-terminal',
            ]
        );

        // ── 17. Fleet & Logistics (Authentic Tunisian Plates & Context) ──
        $vehHilux = Vehicle::updateOrCreate(['plate_number' => '214 TU 4521'], [
            'fleet_code' => 'VEH-0001',
            'label' => 'Toyota Hilux 4x4 Intervention Rapide',
            'make' => 'Toyota',
            'model' => 'Hilux Double Cabine',
            'year' => 2022,
            'vin' => 'AHTFR22G609124501',
            'color' => 'Blanc Arctique',
            'type' => 'pickup',
            'vehicle_class' => 'commercial',
            'capacity_kg' => 1050,
            'seats' => 5,
            'fuel_type' => 'diesel',
            'fuel_tank_capacity' => 80,
            'ownership_type' => 'company',
            'odometer_km' => 64200,
            'status' => Vehicle::STATUS_AVAILABLE,
            'current_responsible_employee_id' => $sami->user_id,
            'default_driver_id' => $sami->user_id,
            'insurance_expiry_date' => now()->addMonths(8)->toDateString(),
            'registration_expiry_date' => now()->addMonths(8)->toDateString(),
            'notes' => 'Véhicule équipé pour interventions extincteurs et RIA tout terrain.',
        ]);

        $vehBoxer = Vehicle::updateOrCreate(['plate_number' => '218 TU 8904'], [
            'fleet_code' => 'VEH-0002',
            'label' => 'Peugeot Boxer Atelier Mobile RIA',
            'make' => 'Peugeot',
            'model' => 'Boxer L2H2',
            'year' => 2023,
            'vin' => 'VF3YBNMFC12890432',
            'color' => 'Blanc Banquise',
            'type' => 'van',
            'vehicle_class' => 'commercial',
            'capacity_kg' => 1500,
            'capacity_m3' => 11.5,
            'seats' => 3,
            'fuel_type' => 'diesel',
            'fuel_tank_capacity' => 90,
            'ownership_type' => 'company',
            'odometer_km' => 38100,
            'status' => Vehicle::STATUS_IN_USE,
            'current_responsible_employee_id' => $bilel->user_id,
            'default_driver_id' => $bilel->user_id,
            'insurance_expiry_date' => now()->addMonths(11)->toDateString(),
            'registration_expiry_date' => now()->addMonths(11)->toDateString(),
            'notes' => 'Fourgon atelier avec banc de charge et outillage hydraulique pour réseaux incendie.',
        ]);

        $vehClio = Vehicle::updateOrCreate(['plate_number' => '222 TU 1230'], [
            'fleet_code' => 'VEH-0003',
            'label' => 'Renault Clio 5 Commercial & Supervision',
            'make' => 'Renault',
            'model' => 'Clio V',
            'year' => 2024,
            'vin' => 'VF1RJA00568912305',
            'color' => 'Gris Titane',
            'type' => 'car',
            'vehicle_class' => 'light',
            'seats' => 5,
            'fuel_type' => 'petrol',
            'fuel_tank_capacity' => 45,
            'ownership_type' => 'company',
            'odometer_km' => 18450,
            'status' => Vehicle::STATUS_AVAILABLE,
            'current_responsible_employee_id' => $employeesByEmail['fares.benyoussef@waveplus.io']->user_id,
            'default_driver_id' => $employeesByEmail['fares.benyoussef@waveplus.io']->user_id,
            'insurance_expiry_date' => now()->addMonths(14)->toDateString(),
            'registration_expiry_date' => now()->addMonths(14)->toDateString(),
            'notes' => 'Véhicule de liaison commerciale et visites clients en Tunisie.',
        ]);

        $vehIsuzu = Vehicle::updateOrCreate(['plate_number' => '225 TU 6745'], [
            'fleet_code' => 'VEH-0004',
            'label' => 'Isuzu NPR Camion Poids Lourd Matériel Incendie',
            'make' => 'Isuzu',
            'model' => 'NPR 75L',
            'year' => 2021,
            'vin' => 'JALNPR75L71006745',
            'color' => 'Bleu Wave Plus',
            'type' => 'truck',
            'vehicle_class' => 'heavy',
            'capacity_kg' => 4500,
            'seats' => 3,
            'fuel_type' => 'diesel',
            'fuel_tank_capacity' => 140,
            'ownership_type' => 'company',
            'odometer_km' => 92600,
            'status' => Vehicle::STATUS_AVAILABLE,
            'current_responsible_employee_id' => $bilel->user_id,
            'default_driver_id' => $bilel->user_id,
            'insurance_expiry_date' => now()->addMonths(5)->toDateString(),
            'registration_expiry_date' => now()->addMonths(5)->toDateString(),
            'notes' => 'Camion de transport de bouteilles de gaz extincteur, poudre et tuyaux industriels.',
        ]);

        // ── 18. HR Approved Drivers ───────────────────────────────────
        $drvBilel = ApprovedDriver::updateOrCreate(
            ['employee_id' => $bilel->id],
            [
                'driver_authorization_number' => 'AUT-DRV-0001',
                'authorization_status' => 'approved',
                'authorization_date' => '2024-02-20',
                'expiry_date' => now()->addYears(3)->toDateString(),
                'license_number' => 'TN-87/094218',
                'license_category' => 'C',
                'license_categories' => ['B', 'C', 'C+E', 'D'],
                'license_issuer' => 'ATTT (Agence Technique des Transports Terrestres)',
                'license_issue_date' => '2012-05-14',
                'license_expiry_date' => now()->addYears(2)->toDateString(),
                'permitted_vehicle_types' => ['pickup', 'van', 'truck', 'car'],
                'permitted_vehicle_classes' => ['light', 'commercial', 'heavy'],
                'can_drive_rental' => true,
                'can_drive_client_vehicles' => true,
                'driver_score' => 98.50,
                'driver_level' => 'expert',
                'notes' => 'Conducteur principal poids lourds WAVE Plus, habilité matières dangereuses.',
            ]
        );

        $drvSami = ApprovedDriver::updateOrCreate(
            ['employee_id' => $sami->id],
            [
                'driver_authorization_number' => 'AUT-DRV-0002',
                'authorization_status' => 'approved',
                'authorization_date' => '2024-01-15',
                'expiry_date' => now()->addYears(2)->toDateString(),
                'license_number' => 'TN-93/114820',
                'license_category' => 'B',
                'license_categories' => ['B'],
                'license_issuer' => 'ATTT Tunis',
                'license_issue_date' => '2015-08-22',
                'license_expiry_date' => now()->addYears(4)->toDateString(),
                'permitted_vehicle_types' => ['pickup', 'car', 'van'],
                'permitted_vehicle_classes' => ['light', 'commercial'],
                'can_drive_rental' => true,
                'can_drive_client_vehicles' => false,
                'driver_score' => 96.00,
                'driver_level' => 'senior',
                'notes' => 'Chef d’équipe habilité à la conduite des véhicules d’intervention.',
            ]
        );

        // ── 19. Vehicle Assignments, Fuel, Maintenance & Checklists ───
        VehicleAssignment::updateOrCreate(
            ['vehicle_id' => $vehBoxer->id, 'responsible_employee_id' => $bilel->user_id, 'is_current' => true],
            [
                'custody_type' => 'company_site',
                'location_notes' => 'Siège WAVE Plus & Z.I. Charguia 2',
                'start_date' => now()->subMonths(3),
                'odometer_at_start' => 32000,
                'reason' => 'Affectation opérationnelle logistique et livraisons RIA',
            ]
        );

        // Fuel record in Tunisian Dinars (TND) at Agil / Total Station
        VehicleFuelRecord::updateOrCreate(
            ['vehicle_id' => $vehBoxer->id, 'receipt_number' => 'REC-AGIL-2026-901'],
            [
                'fill_date' => now()->subDays(2)->toDateString(),
                'fill_time' => '08:45:00',
                'fuel_type' => 'diesel',
                'quantity' => 70.000,
                'unit' => 'L',
                'odometer_km' => 38050,
                'unit_price' => 2.250,
                'total_cost' => 157.500,
                'currency' => 'TND',
                'supplier' => 'SNDP Agil',
                'station' => 'Station Agil Charguia 2',
                'city' => 'Tunis',
                'payment_method' => 'fleet_card',
                'card_last_four' => '4410',
                'filled_by_employee_id' => $bilel->user_id,
            ]
        );

        // Maintenance record
        VehicleMaintenanceRecord::updateOrCreate(
            ['maintenance_number' => 'MNT-2026-0042'],
            [
                'vehicle_id' => $vehBoxer->id,
                'maintenance_type' => 'preventive',
                'priority' => 'normal',
                'scheduled_date' => now()->subDays(15)->toDateString(),
                'completed_date' => now()->subDays(14)->toDateString(),
                'odometer_km' => 37500,
                'workshop_name' => 'Atelier WAVE Plus & Garage Central Megrine',
                'title' => 'Vidange complète 35 000 km et filtres',
                'description' => 'Changement huile moteur 5W30, filtre à huile, filtre à gazole et filtre d’habitacle.',
                'total_cost' => 420.000,
                'currency' => 'TND',
                'status' => 'completed',
            ]
        );

        // Vehicle Checklist
        VehicleChecklist::updateOrCreate(
            ['vehicle_id' => $vehBoxer->id, 'title' => 'Inspection journalière pré-départ'],
            [
                'checklist_type' => 'daily_inspection',
                'completed_at' => now()->subDays(1)->setHour(7)->setMinute(45),
                'completed_by_employee_id' => $bilel->user_id,
                'driver_id' => $bilel->user_id,
                'odometer_km' => 38020,
                'location' => 'Charguia 2 Depot',
                'checklist_items' => [
                    ['item' => 'Niveau huile & liquide de refroidissement', 'status' => 'ok'],
                    ['item' => 'Pression des pneus & roue de secours', 'status' => 'ok'],
                    ['item' => 'Éclairage feux avant / arrière / clignotants', 'status' => 'ok'],
                    ['item' => 'Freinage & témoin tableau de bord', 'status' => 'ok'],
                    ['item' => 'Extincteur de bord 2kg vérifié', 'status' => 'ok'],
                ],
                'overall_status' => 'passed',
                'notes' => 'Véhicule en parfait état de fonctionnement.',
            ]
        );

        // Driver Incident (minor for testing incident reporting)
        DriverIncident::updateOrCreate(
            ['incident_number' => 'INC-DRV-2026-001'],
            [
                'driver_id' => $drvBilel->id,
                'employee_id' => $bilel->id,
                'vehicle_id' => $vehBoxer->id,
                'incident_date' => now()->subMonths(2)->setHour(14)->setMinute(20),
                'incident_type' => 'equipment_damage',
                'severity' => 'minor',
                'title' => 'Frottement mineur barrière basse',
                'location' => 'Parking Zone Industrielle Ben Arous',
                'city' => 'Ben Arous',
                'description' => 'Léger frottement contre barrière de chargement basse lors d’une marche arrière.',
                'estimated_cost' => 120.000,
                'currency' => 'TND',
                'status' => 'closed',
            ]
        );

        // ── 20. Recruitment Openings & Candidates ─────────────────────
        $job = JobOpening::firstOrCreate(['reference' => 'JOB-00001'], [
            'title' => 'Senior Fire Safety Systems Specialist',
            'department_id' => $intervention->id,
            'position_id' => $posFireTech->id,
            'openings_count' => 2,
            'status' => 'published',
            'description' => 'Looking for experienced fire safety technicians to service industrial client accounts in Tunis & Sfax.',
            'published_at' => now()->subDays(10),
        ]);

        Candidate::firstOrCreate(['email' => 'tarek.benamor@candidate.tn'], [
            'first_name' => 'Tarek',
            'last_name' => 'Ben Amor',
            'phone' => '+216 98 123 456',
            'status' => 'interview',
            'notes' => '5 years experience in extinguisher maintenance and SSIAP 1 certified in Tunisia.',
        ]);

        Candidate::firstOrCreate(['email' => 'ines.driss@candidate.tn'], [
            'first_name' => 'Ines',
            'last_name' => 'Driss',
            'phone' => '+216 97 888 999',
            'status' => 'offer',
            'notes' => 'Selected candidate. Offer letter extended for RIA specialist role.',
        ]);
    }

    /**
     * Build the locale payload expected by Spatie Translatable fields.
     */
    private const FIELD_TRANSLATIONS = [
        'WAVE Plus Headquarters' => ['fr' => 'Siège de WAVE Plus', 'ar' => 'المقر الرئيسي لـ WAVE Plus'],
        'Operations' => ['fr' => 'Opérations', 'ar' => 'العمليات'],
        'Intervention & Maintenance' => ['fr' => 'Intervention et maintenance', 'ar' => 'التدخل والصيانة'],
        'Workshop & Technical Lab' => ['fr' => 'Atelier et laboratoire technique', 'ar' => 'الورشة والمختبر التقني'],
        'Logistics & Fleet' => ['fr' => 'Logistique et parc automobile', 'ar' => 'اللوجستيات وأسطول المركبات'],
        'Warehouse & Inventory' => ['fr' => 'Magasin et gestion des stocks', 'ar' => 'المستودع وإدارة المخزون'],
        'Human Resources & Talent' => ['fr' => 'Ressources humaines et talents', 'ar' => 'الموارد البشرية والكفاءات'],
        'Commercial & Sales' => ['fr' => 'Activités commerciales et ventes', 'ar' => 'الأنشطة التجارية والمبيعات'],
        'Operations Manager' => ['fr' => 'Responsable des opérations', 'ar' => 'مدير العمليات'],
        'Team Leader' => ['fr' => 'Chef d’équipe', 'ar' => 'قائد الفريق'],
        'Fire Safety Technician' => ['fr' => 'Technicien en sécurité incendie', 'ar' => 'فني السلامة من الحرائق'],
        'Workshop Technician' => ['fr' => 'Technicien d’atelier', 'ar' => 'فني ورشة'],
        'Fleet Logistics Driver' => ['fr' => 'Conducteur logistique et flotte', 'ar' => 'سائق اللوجستيات وأسطول المركبات'],
        'Warehouse Operator' => ['fr' => 'Agent de magasin', 'ar' => 'عامل المستودع'],
        'HR & People Manager' => ['fr' => 'Responsable RH et gestion du personnel', 'ar' => 'مسؤول الموارد البشرية وشؤون الموظفين'],
        'Sales Coordinator' => ['fr' => 'Coordinateur commercial', 'ar' => 'منسق المبيعات'],
        'Electrical Maintenance Technician' => ['fr' => 'Technicien de maintenance électrique', 'ar' => 'فني صيانة كهربائية'],
        'HSE Coordinator' => ['fr' => 'Coordinateur HSE (hygiène, sécurité et environnement)', 'ar' => 'منسق الصحة والسلامة والبيئة'],
        'Team Alpha (Intervention)' => ['fr' => 'Équipe Alpha (intervention)', 'ar' => 'الفريق ألفا (التدخلات)'],
        'Team Bravo (Maintenance)' => ['fr' => 'Équipe Bravo (maintenance)', 'ar' => 'الفريق برافو (الصيانة)'],
        'Emergency Response Team' => ['fr' => 'Équipe d’intervention d’urgence', 'ar' => 'فريق الاستجابة للطوارئ'],
        'Workshop Crew' => ['fr' => 'Équipe de l’atelier', 'ar' => 'فريق الورشة'],
        'Logistics Drivers' => ['fr' => 'Conducteurs logistiques', 'ar' => 'سائقو الخدمات اللوجستية'],
        'Fire Extinguisher Maintenance' => ['fr' => 'Maintenance des extincteurs', 'ar' => 'صيانة مطفآت الحريق'],
        'RIA & Hose Reels Maintenance' => ['fr' => 'Maintenance des RIA et dévidoirs incendie', 'ar' => 'صيانة بكرات وخراطيم مكافحة الحريق'],
        'Hydrant System Servicing' => ['fr' => 'Maintenance des réseaux de poteaux incendie', 'ar' => 'صيانة شبكات صنابير الحريق'],
        'Fire Pump Operations & Testing' => ['fr' => 'Exploitation et essais des pompes incendie', 'ar' => 'تشغيل واختبار مضخات الحريق'],
        'Fire Detection & Alarm Systems' => ['fr' => 'Systèmes de détection incendie et d’alarme', 'ar' => 'أنظمة كشف الحرائق والإنذار'],
        'Electrical Maintenance & Diagnostic' => ['fr' => 'Maintenance et diagnostic électriques', 'ar' => 'الصيانة الكهربائية والتشخيص'],
        'Hydraulic Overhaul' => ['fr' => 'Révision des systèmes hydrauliques', 'ar' => 'الصيانة الشاملة للأنظمة الهيدروليكية'],
        'Industrial Welding' => ['fr' => 'Soudage industriel', 'ar' => 'اللحام الصناعي'],
        'Heavy & Commercial Driving' => ['fr' => 'Conduite de poids lourds et de véhicules commerciaux', 'ar' => 'قيادة الشاحنات الثقيلة والمركبات التجارية'],
        'Working at Height' => ['fr' => 'Travail en hauteur', 'ar' => 'العمل في المرتفعات'],
        'Confined Space Intervention' => ['fr' => 'Intervention en espace confiné', 'ar' => 'التدخل في الأماكن المغلقة'],
        'Fire Safety' => ['fr' => 'Sécurité incendie', 'ar' => 'السلامة من الحرائق'],
        'Health, Safety & Environment' => ['fr' => 'Hygiène, sécurité et environnement', 'ar' => 'الصحة والسلامة والبيئة'],
        'Customer Service' => ['fr' => 'Service à la clientèle', 'ar' => 'خدمة العملاء'],
        'Team Management' => ['fr' => 'Gestion d’équipe', 'ar' => 'إدارة الفريق'],
        'First Aid' => ['fr' => 'Premiers secours', 'ar' => 'الإسعافات الأولية'],
        'Technical Inspection' => ['fr' => 'Inspection technique', 'ar' => 'الفحص الفني'],
        'Professional Driving License (Class C)' => ['fr' => 'Permis de conduire professionnel (catégorie C)', 'ar' => 'رخصة قيادة مهنية (الصنف ج)'],
        'Ministry of Transport' => ['fr' => 'Ministère du Transport', 'ar' => 'وزارة النقل'],
        'First Aid at Work (SST / Sauveteur Secouriste)' => ['fr' => 'Secourisme au travail (SST)', 'ar' => 'الإسعافات الأولية في مكان العمل'],
        'National Safety Institute' => ['fr' => 'Institut national de sécurité', 'ar' => 'المعهد الوطني للسلامة'],
        'Working at Height Authorization' => ['fr' => 'Autorisation de travail en hauteur', 'ar' => 'ترخيص العمل في المرتفعات'],
        'Bureau Veritas' => ['fr' => 'Bureau Veritas', 'ar' => 'Bureau Veritas'],
        'Fire Safety Qualification (SSIAP 1/2)' => ['fr' => 'Qualification en sécurité incendie (SSIAP 1/2)', 'ar' => 'شهادة التأهيل في السلامة من الحرائق (SSIAP 1/2)'],
        'Civil Protection Board' => ['fr' => 'Direction de la Protection civile', 'ar' => 'الديوان الوطني للحماية المدنية'],
        'Electrical Certification (BR / B2V)' => ['fr' => 'Habilitation électrique (BR / B2V)', 'ar' => 'اعتماد السلامة الكهربائية (BR / B2V)'],
        'Apave Safety' => ['fr' => 'Apave Sécurité', 'ar' => 'Apave للسلامة'],
        'Forklift Operator Certificate (CACES R489)' => ['fr' => 'Certificat de conduite de chariot élévateur (CACES R489)', 'ar' => 'شهادة قيادة الرافعات الشوكية (CACES R489)'],
        'CACES Board' => ['fr' => 'Organisme CACES', 'ar' => 'جهة اعتماد CACES'],
        'Standard 48-Hour Regime Shift' => ['fr' => 'Horaire Régime 48h (Standard Tunisie)', 'ar' => 'توقيت نظام 48 ساعة (المعياري بتونس)'],
        'Administrative 40-Hour Regime Shift' => ['fr' => 'Horaire Régime 40h (Administratif)', 'ar' => 'توقيت نظام 40 ساعة (إداري)'],
        'Winter Shift' => ['fr' => 'Horaire d’hiver', 'ar' => 'توقيت الشتاء'],
        'Summer Shift' => ['fr' => 'Horaire d’été (Séance Unique)', 'ar' => 'التوقيت الصيفي (حصة واحدة)'],
        'Saturday Shift' => ['fr' => 'Horaire du samedi (Séance matinale)', 'ar' => 'توقيت يوم السبت (صباحي)'],
        'Ramadan Shift' => ['fr' => 'Horaire du Ramadan (Séance Unique)', 'ar' => 'توقيت رمضان (حصة واحدة)'],
        '24h On-Call Emergency Guard' => ['fr' => 'Garde 24h & Astreinte d’urgence', 'ar' => 'نوبة طوارئ واستمرار 24 ساعة'],
        'Paid Annual Leave' => ['fr' => 'Congé annuel payé (21 jours/an)', 'ar' => 'إجازة سنوية مدفوعة الأجر (21 يوماً)'],
        'Sick Leave' => ['fr' => 'Congé maladie (avec certificat CNSS)', 'ar' => 'إجازة مرضية'],
        'Exceptional Family Leave' => ['fr' => 'Congé familial exceptionnel (Mariage, Décès, Naissance)', 'ar' => 'إجازة عائلية استثنائية'],
        'Maternity Leave' => ['fr' => 'Congé de maternité', 'ar' => 'عطلة أمومة'],
        'Paternity Leave' => ['fr' => 'Congé de paternité', 'ar' => 'عطلة أبوة'],
        'Unpaid Leave' => ['fr' => 'Congé sans solde', 'ar' => 'إجازة دون أجر'],
        'Training & Development Leave' => ['fr' => 'Congé de formation et de développement', 'ar' => 'إجازة للتدريب والتطوير'],
        'Advanced Fire Extinguisher & RIA Servicing' => ['fr' => 'Maintenance avancée des extincteurs et des RIA', 'ar' => 'الصيانة المتقدمة لمطفآت الحريق وبكرات الخراطيم'],
        'WAVE Academy & Safety Training' => ['fr' => 'WAVE Academy et formation à la sécurité', 'ar' => 'أكاديمية WAVE والتدريب على السلامة'],
        'Fall Protection & Working at Height Certification' => ['fr' => 'Certification en protection contre les chutes et travail en hauteur', 'ar' => 'شهادة الوقاية من السقوط والعمل في المرتفعات'],
        'Apave Industrial Safety' => ['fr' => 'Apave Sécurité industrielle', 'ar' => 'Apave للسلامة الصناعية'],
        'Mohamed Ben Ali (Master Instructor)' => ['fr' => 'Mohamed Ben Ali (formateur principal)', 'ar' => 'محمد بن علي (مدرب رئيسي)'],
    ];

    private function translations(string $english): array
    {
        $localized = self::FIELD_TRANSLATIONS[$english] ?? ['fr' => $english, 'ar' => $english];

        return [
            'en' => $english,
            'fr' => $localized['fr'] ?? $english,
            'ar' => $localized['ar'] ?? $english,
        ];
    }
}
