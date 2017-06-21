<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Employee;

class ScheduleController extends Controller
{
    public function index() {
        return view ('schedule.index');
    }

    public function create() {
        $conveyorLines = range(1,32);
        $masteredLines = range(1,32);
        return view('schedule.create', compact('conveyorLines', 'masteredLines'));
    }

    public function generate(Request $request) {

        $conveyorLines = $request['conveyor_line'];
        $masteredLines = $request['mastered_line'];

        $count = 0; $i = intval($conveyorLines);
        $line = []; $schedule_array = [];
        $labeler_array = [];
        $index = 1;
        $employees = Employee::all();

        // Generate Line Setup for Conveyor Lines
        while($i >= 0) {
            $labelerSet = false; $labeler = '';
            foreach ($employees as $employee) {
                if($employee->labeler && !($labelerSet)) {
                    if(!(array_search($employee->empname, $labeler_array, true))) {
                        $labeler = $employee->empname;
                        $labelerSet = true;
                        $count++;
                        $labeler_array[$index++] = $employee->empname;
                    }
                }
                if($labelerSet) {
                    break;
                }
            }
            if($labeler == '') {
                $labeler = 'T';
                $count++;
            }
            $line = ['line_number' => $count, 'labeler' => $labeler, 'icer' => 'T'];
            array_push($schedule_array, $line);
            $i--;
        }

        $j = intval($masteredLines);
        $line = [];
        $schedule_array_2 = [];
        $stocker_array = []; $stock_index = 1;

        // Generate Line Setup for Mastered Lines
        while($j >= 0) {
            $labelerSet = false; $stockerSet = false;
            $labeler = ''; $stocker = '';
            foreach ($employees as $employee) {
                if ($employee->labeler && !($labelerSet)) {
                    if(!(array_search($employee->empname, $labeler_array, true))) {
                        $labeler = $employee->empname;
                        $labelerSet = true;
                        $labeler_array[$index++] = $employee->empname;
                    }
                } elseif ($employee->stocker && !($stockerSet)) {
                    if(!(array_search($employee->empname, $stocker_array, true))) {
                        $stocker = $employee->empname;
                        $stockerSet = true;
                        $stocker_array[$stock_index++] = $employee->empname;
                        $count++;
                    }
                }
                if($labelerSet && $stockerSet) {
                    break;
                }
            }
            // If Labeler and Stocker are not set, set them as Default Temps
            if($labeler == '') {
                $labeler = 'T';
            }
            if ($stocker == '') {
                $stocker = 'T';
                $count++;
            }
            $line = ['line_number' => $count, 'labeler' => $labeler, 'stocker' => $stocker, 'icer' => 'T'];
            array_push($schedule_array_2, $line);
            $j--;
        }

        return view ('schedule.generate', compact('schedule_array', 'schedule_array_2'));
    }
}
