<?php

namespace App\Exports;

use App\Models\AcademicPeriod;
use App\Models\Career;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SemesterScheduleExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $careerId, $cycle, $shiftId, $periodId;

    public function __construct($careerId, $cycle, $shiftId, $periodId)
    {
        $this->careerId = $careerId;
        $this->cycle = $cycle;
        $this->shiftId = $shiftId;
        $this->periodId = $periodId;
    }

    public function view(): View
    {
        $schedules = Schedule::with(['teacherAssignment.teacher.user', 'teacherAssignment.didacticUnit', 'classroomResource'])
            ->whereHas('teacherAssignment', function ($q) {
                $q->where('academic_period_id', $this->periodId)
                    ->where('shift_id', $this->shiftId)
                    ->whereHas('didacticUnit', function ($q2) {
                        $q2->where('semester', $this->cycle)
                            ->whereHas('module.studyPlan', fn($q3) => $q3->where('career_id', $this->careerId));
                    });
            })->get()->groupBy('day_of_week');

        return view('reports.schedules.consolidated-export', [
            'schedules' => $schedules,
            'career'    => Career::find($this->careerId),
            'shift'     => Shift::find($this->shiftId),
            'period'    => AcademicPeriod::find($this->periodId),
            'cycle'     => $this->cycle,
            'isExcel'   => true
        ]);

        
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
        ];
    }
}
