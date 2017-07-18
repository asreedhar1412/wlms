<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Employee;
use App\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Validator;

class ScheduleController extends Controller
{

    public function __construct()
    {
        //Add middleware auth for refresh
        $this->schedule_array = [];
        $this->schedule_array_2 = [];
        $this->mezzanineArray = [];
        $this->runnerArray = [];

        $this->viewData = ['schedule_array' => $this->schedule_array, 'schedule_array_2' => $this->schedule_array_2, 'mezzanineArray' => $this->mezzanineArray, 'runnerArray' => $this->runnerArray];
    }

    public function index() {
        return view ('schedule.index');
    }

    public function create() {
        $conveyorLines = range(0,12);
        $supportLines = range(0,24);
        return view('schedule.create', compact('conveyorLines', 'supportLines'));
    }

    public function generate(Request $request) {


        $validator = Validator::make($request->all(), [
            'schedule_date' => 'bail|required',
            'schedule_time' => 'required',
            'conveyor_line' => 'required|numeric',
            'support_line' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return redirect('/schedule/create')->withErrors($validator)->withInput();
        }

        $conveyorLines = $request['conveyor_line'];
        $supportLines = $request['support_line'];

        $count = 0; $i = intval($conveyorLines);
        $line = [];
        $labeler_array = []; $icerArray = [];
        $index = 1; $icerIndex = 1;
        $employees = Employee::all();

        // Generate Line Setup for Conveyor Lines
        while($i > 0) {
            $labelerSet = false; $labeler = ''; $icerSet = false; $icer = '';
            foreach ($employees as $employee) {
                if($employee->labeler && !($labelerSet)) {
                    if(!(array_search($employee->empname, $labeler_array, true)) && !(array_search($employee->empname, $icerArray, true))) {
                        $labeler = $employee->empname;
                        $labelerSet = true;
                        $labeler_array[$index++] = $employee->empname;
                    }
                } elseif ($employee->icer && !($icerSet)) {
                    if(!(array_search($employee->empname, $labeler_array, true)) && !(array_search($employee->empname, $icerArray, true))) {
                        $icer = $employee->empname;
                        $icerSet = true;
                        $icerArray[$icerIndex] = $employee->empname;
                        $count++;
                    }
                }
                if($labelerSet && $icerSet) {
                    break;
                }
            }
            if($labeler == '') {
                $labeler = 'Temp';
            }
            if ($icer == '') {
                $icer = 'Temp';
                $count++;
            }
            $line = ['line_number' => $count, 'labeler' => $labeler, 'icer' => $icer];
            array_push($this->schedule_array, $line);
            $i--;
        }

        $j = intval($supportLines);
        $line = [];
        $count = 12; // Set Count to the end of Conveyor Line Number
        $stocker_array = []; $stock_index = 1;

        // Generate Line Setup for Support Lines
        while($j > 0) {
            $labelerSet = false; $stockerSet = false; $icerSet = false;
            $labeler = ''; $stocker = ''; $icer = '';
            foreach ($employees as $employee) {
                if ($employee->labeler && !($labelerSet)) {
                    if(!(array_search($employee->empname, $labeler_array, true)) && !(array_search($employee->empname, $stocker_array, true)) && !(array_search($employee->empname, $icerArray, true))) {
                        $labeler = $employee->empname;
                        $labelerSet = true;
                        $labeler_array[$index++] = $employee->empname;
                    }
                } elseif ($employee->stocker && !($stockerSet)) {
                    if(!(array_search($employee->empname, $stocker_array, true)) && !(array_search($employee->empname, $labeler_array, true)) && !(array_search($employee->empname, $icerArray, true))) {
                        $stocker = $employee->empname;
                        $stockerSet = true;
                        $stocker_array[$stock_index++] = $employee->empname;
                    }
                } elseif ($employee->icer && !($icerSet)) {
                    if(!(array_search($employee->empname, $stocker_array, true)) && !(array_search($employee->empname, $labeler_array, true)) && !(array_search($employee->empname, $icerArray, true))) {
                        $icer = $employee->empname;
                        $icerSet = true;
                        $icerArray[$icerIndex++] = $employee->empname;
                        $count++;
                    }
                }
                if($labelerSet && $stockerSet && $icerSet) {
                    break;
                }
            }

            // If Labeler and Stocker are not set, set them as Default Temps
            if($labeler == '') {
                $labeler = 'Temp';
            }
            if ($stocker == '') {
                $stocker = 'Temp';
            }
            if($icer == '') {
                $icer = 'Temp';
                $count++;
            }
            $line = ['line_number' => $count, 'labeler' => $labeler, 'stocker' => $stocker, 'icer' => $icer];
            array_push($this->schedule_array_2, $line);
            $j--;
        }

        //Create Line Setup for Mezzanine
        $flag = true;
        $lineArray = $this->createLineSetup($conveyorLines, $supportLines, $flag);


        //Array for Mezzanine
        $total_lines = intval($conveyorLines) + intval($supportLines);
        $numOfMezzanineWorkers = intval($total_lines / 3);
        if(intval($total_lines) % 3 != 0) {
            $numOfMezzanineWorkers += 1;
        }
        //Index to maintain in array
        $mezIndex = 1; $k = 0;
        //Array to save assigned workers
        $mezArray = [];

        while ($k < $numOfMezzanineWorkers) {
            $mezzanine = 'Temp';$mezzanineSet = false;
            foreach ($employees as $employee) {
                if ($employee->mezzanine && !($mezzanineSet)) {
                    if (!(array_search($employee->empname, $labeler_array, true)) && !(array_search($employee->empname, $stocker_array, true)) && !(array_search($employee->empname, $mezArray, true)) && !(array_search($employee->empname, $icerArray, true))) {
                        $mezzanine = $employee->empname;
                        $mezArray[$mezIndex++] = $mezzanine;
                        $mezzanineSet = true;
                    }
                }
                if($mezzanineSet) {
                    break;
                }
            }
            $line = ['lines' => $lineArray[$k], 'name' => $mezzanine];
            array_push($this->mezzanineArray, $line);
            $k++;
        }

        //Create Line Setup for Runners
        $flag = false;
        $lineArray = $this->createLineSetup($conveyorLines, $supportLines, $flag);

        //Array for Runners in Schedule
        $numOfRunners = intval($total_lines / 6);
        if(intval($total_lines % 6) != 0) {
            $numOfRunners += 1;
        }
        $runnerIndex = 1; $r = 0;
        //Array to save assigned runners
        $runnerArray = [];

        while($r < $numOfRunners) {
            $runner = 'Temp'; $runnerSet = false;
            foreach ($employees as $employee) {
                if($employee->runner && !($runnerSet)) {
                    if (!(array_search($employee->empname, $labeler_array, true)) && !(array_search($employee->empname, $stocker_array, true))
                        && !(array_search($employee->empname, $mezArray, true)) && !(array_search($employee->empname, $runnerArray, true))
                        && !(array_search($employee->empname, $icerArray, true))) {

                        $runner = $employee->empname;
                        $runnerArray[$runnerIndex] = $runner;
                        $runnerSet = true;
                    }
                }
                if($runnerSet) {
                    break;
                }
            }
            $line = ['lines' => $lineArray[$r], 'name' => $runner];
            array_push($this->runnerArray, $line);
            $r++;
        }



        $this->viewData['schedule_array'] = $this->schedule_array;
        $this->viewData['schedule_array_2'] = $this->schedule_array_2;
        $this->viewData['mezzanineArray'] = $this->mezzanineArray;
        $this->viewData['runnerArray'] = $this->runnerArray;


        $timeOfSchedule = $request['schedule_time'];
        $coolersShipped = $request['coolers_shipped'];
        $scheduleDate = $request['schedule_date'];
        //Save the Schedule Array as JSON in Database
        $schedule = new Schedule();
        $schedule->schedule = json_encode($this->viewData);
        $schedule->coolers_shipped = $coolersShipped;
        $schedule->date = $scheduleDate;
        $schedule->time = $timeOfSchedule;
        $schedule->save();

        $currentSchedule = Schedule::all()->last()->id;
        $this->viewData['id'] = $currentSchedule;

        return view ('schedule.generate', $this->viewData);
    }


    public function createLineSetup($cLines, $sLines, $flag) {
        $divisor = 1;
        if ($flag) {
            $divisor = 3;
        } else {
            $divisor = 6;
        }

        $workers = intval($cLines / $divisor);
        if(intval($cLines) % $divisor != 0) {
            $workers += 1;
        }

        $arr = $this->distributeLines($cLines, $workers);
        $startIndexer = $this->schedule_array[0]['line_number']; $endIndex = ($this->schedule_array[0]['line_number'] - 1); $setupIndex = 0;
        $lineArray = [];

        //Create line number setup
        for($i = 0; $i < sizeof($arr); $i++) {
            $endIndex += $arr[$i];
            $lineArray [$setupIndex] = $startIndexer . '-' . $endIndex;
            $startIndexer+= $arr[$i];
            $setupIndex++;
        }

        $workers = intval($sLines / $divisor);
        if(intval($sLines) % $divisor != 0) {
            $workers += 1;
        }

        $arr = $this->distributeLines($sLines, $workers);
        $startIndexer = $this->schedule_array_2[0]['line_number']; $endIndex = ($this->schedule_array_2[0]['line_number'] - 1);
        //Create line number setup
        for($i = 0; $i < sizeof($arr); $i++) {
            $endIndex += $arr[$i];
            $lineArray [$setupIndex] = $startIndexer . '-' . $endIndex;
            $startIndexer+= $arr[$i];
            $setupIndex++;
        }
        return $lineArray;
    }

    public function distributeLines($lines, $numOfWorkers) {

        $arr = [];
        for($i = 0; $i < $numOfWorkers; $i++) {
            $arr[$i] = intval($lines / $numOfWorkers);
        }

        $keys = array_keys($arr);
        $index = end($keys);

        for ($i = ($lines % $numOfWorkers); $i > 0; $i--) {
            $arr[$index] += 1;
            $index--;
        }
        return $arr;
    }


    public function downloadReport(Request $request) {

        $scheduler = Schedule::all()->last();
        $timeOfSchedule = $scheduler->time;
        $coolersShipped = $scheduler->coolers_shipped;
        $scheduleDate = $scheduler->date;
        $this->viewData = json_decode($scheduler->schedule, true);
        $labelerArray = $this->viewData['schedule_array'];
        $supportLineArray = $this->viewData['schedule_array_2'];
        $runnerArray = $this->viewData['runnerArray'];
        $mezzanineArray = $this->viewData['mezzanineArray'];

        Excel::create('Schedule', function($excel) use ($timeOfSchedule, $scheduleDate, $labelerArray, $supportLineArray, $mezzanineArray, $runnerArray, $coolersShipped) {
            $excel->sheet('Lineup', function($sheet) use ($timeOfSchedule, $scheduleDate, $labelerArray, $supportLineArray, $mezzanineArray, $runnerArray, $coolersShipped) {
                $sheet->cell('I1', function ($cell) {
                    $cell->setValue('Time');
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(16);
                });

                $sheet->getRowDimension(1)->setRowHeight(10);
                $sheet->getColumnDimension('A')->setWidth(100);

                $sheet->cell('J1', function ($cell) use ($timeOfSchedule) {
                    $cell->setValue($timeOfSchedule);
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(16);
                });
                $sheet->mergeCells('K1:L1');
                $sheet->cell('K1', function($cell) {
                    $cell->setValue('Coolers Shipped');
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(16);
                });
                $sheet->cell('M1', function($cell) use ($coolersShipped) {
                    $cell->setValue($coolersShipped);
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(16);
                });
                $sheet->cell('N1', function ($cell) {
                    $cell->setValue('Date');
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(16);
                });
                $sheet->cell('O1', function ($cell) use ($scheduleDate) {
                    $cell->setValue($scheduleDate);
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(16);
                });

                // Fill Conveyor Lines in Excel Sheet Format
                $column = 'A'; $row = 5;
                $lineNumber = 12;

                for ($i = 0; $i < 12; $i++) {

                    $cellNumber = $column . $row;

                    $sheet->cell($cellNumber, function ($cell) use ($lineNumber) {
                        $cellValue = 'LINE #' . $lineNumber;
                        $cell->setValue($cellValue);
                        $cell->setFontWeight($bold = true);
                        $cell->setFontSize(14);

                    });
                    $column++;
                    $column++;
                    $lineNumber--;
                }


                $column = 'A'; $row = 6;
                for ($i = 0; $i < 12; $i++) {
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) {
                        $cellValue = 'Labeler';
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column++;
                    $column++;
                }


                $column = 'A'; $row = 9;
                for ($i = 0; $i < 12; $i++) {
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) {
                        $cellValue = 'Icer';
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column++;
                    $column++;
                }


                //Fill values for Labeler
                $column = 'W'; $row = 7;
                for ($index = 0; $index < sizeof($labelerArray); $index++) {
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) use ($labelerArray, $index) {
                        //global $labelerArray;
                        //global $index;
                        $cellValue = $labelerArray[$index]['labeler'];
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column = chr(ord($column) -  1);
                    $column = chr(ord($column) -  1);
                }

                //Fill values for Icer
                $column = 'W'; $row = 10;
                for ($index = 0; $index < sizeof($labelerArray); $index++) {
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) {
                        $cellValue = 'Temp';
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column = chr(ord($column) -  1);
                    $column = chr(ord($column) -  1);
                }


                // Fill Support Lines
                $column = 'A'; $row = 14;
                $lineNumber = 24;
                for ($i = 0; $i < 24; $i++) {
                    //After first row of Support Lines are set, reset for 2nd row
                    //Setting Line Numbers for Support Line
                    if($lineNumber == 12) {
                        $column = 'A'; $row = 26; $lineNumber = 36;
                    }
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) use($lineNumber) {
                        $cellValue = 'LINE #' . $lineNumber;
                        $cell->setValue($cellValue);
                        $cell->setFontWeight($bold = true);
                        $cell->setFontSize(14);

                    });
                    $column++;
                    $column++;
                    $lineNumber--;
                }


                $column = 'A'; $row = 15;
                $lineNumber = 24;
                for ($i = 0; $i < 24; $i++) {
                    //After first row of Support Lines are set, reset for 2nd row
                    //Setting Labeler Heading for Support Line
                    if($lineNumber == 12) {
                        $column = 'A'; $row = 27; $lineNumber = 36;
                    }
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) {
                        $cellValue = 'Labeler';
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column++;
                    $column++;
                    $lineNumber--;
                }

                $column = 'A'; $row = 18;
                $lineNumber = 24;
                for ($i = 0; $i < 24; $i++) {
                    //After first row of Support Lines are set, reset for 2nd row
                    //Setting Stocker Heading for Support Line
                    if($lineNumber == 12) {
                        $column = 'A'; $row = 30; $lineNumber = 36;
                    }
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) {
                        $cellValue = 'Stocker';
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);

                    });
                    $column++;
                    $column++;
                    $lineNumber--;
                }


                $column = 'A'; $row = 21;
                $lineNumber = 24;
                for ($i = 0; $i < 24; $i++) {
                    //After first row of Support Lines are set, reset for 2nd row
                    //Setting Icer Heading for Support Line
                    if($lineNumber == 12) {
                        $column = 'A'; $row = 32; $lineNumber = 36;
                    }
                    $cellNumber = $column . $row;

                    $sheet->cell($cellNumber, function ($cell) {
                        $cellValue = 'Icer';
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column++;
                    $column++;
                    $lineNumber--;
                }

                //Fill values for Labeler
                $column = 'W'; $row = 16;
                for ($index = 0; $index < sizeof($supportLineArray); $index++) {
                    if($index == 12) {
                        $column = 'W';
                        $row = 28;
                    }
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) use ($supportLineArray, $index) {
                        $cellValue = $supportLineArray[$index]['labeler'];
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column = chr(ord($column) -  1);
                    $column = chr(ord($column) -  1);
                }


                //Fill values for Stocker
                $column = 'W'; $row = 19;
                for ($index = 0; $index < sizeof($supportLineArray); $index++) {
                    if($index == 12) {
                        $column = 'W';
                        $row = 31;
                    }
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) use ($supportLineArray, $index) {
                        $cellValue = $supportLineArray[$index]['stocker'];
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column = chr(ord($column) -  1);
                    $column = chr(ord($column) -  1);
                }


                //Fill values for Icer
                $column = 'W'; $row = 22;
                for ($index = 0; $index < sizeof($supportLineArray); $index++) {
                    if($index == 12) {
                        $column = 'W';
                        $row = 33;
                    }
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) use ($supportLineArray, $index) {
                        $cellValue = $supportLineArray[$index]['icer'];
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column = chr(ord($column) -  1);
                    $column = chr(ord($column) -  1);
                }

                //$column = 'A'; $row = 35;
                $sheet->cell('A35', function ($cell) {
                    $cell->setValue('Mezzanine');
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(16);
                });

                $sheet->cell('A36', function ($cell) {
                    $cell->setValue('Lines');
                    $cell->setFontWeight($bold = true);
                    $cell->setFontSize(14);
                });

                $column = 'B'; $row = 35;
                for ($index = 0; $index < sizeof($mezzanineArray); $index++) {
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) use ($mezzanineArray, $index) {
                        $cellValue = $mezzanineArray[$index]['name'];
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column++;
                }

                $column = 'B'; $row = 36;
                for ($index = 0; $index < sizeof($mezzanineArray); $index++) {
                    $cellNumber = $column . $row;
                    $sheet->cell($cellNumber, function ($cell) use ($mezzanineArray, $index) {
                        $cellValue = $mezzanineArray[$index]['lines'];
                        $cell->setValue($cellValue);
                        $cell->setFontSize(14);
                    });
                    $column++;
                }



            });
        })->download('xls');

    }

    public function show() {
        echo "Show Function to be implemented with View Old Schedules";
    }

    public function edit($id) {

        $scheduler = Schedule::find($id);

        $empLabelers = Employee::where('labeler', true)->pluck('empname')->toArray();
        $empStockers = Employee::where('stocker', true)->pluck('empname')->toArray();
        $empIcers = Employee::where('icer', true)->pluck('empname')->toArray();
        $empRunners = Employee::where('runner', true)->pluck('empname')->toArray();
        $empMezzanines = Employee::where('mezzanine', true)->pluck('empname')->toArray();

        $employees = Employee::all();
        $empList = $employees->pluck('empname')->toArray();

       // dd($empList);

        $this->viewData = json_decode($scheduler->schedule, true);
        $currentSchedule['schedule_array'] = $this->viewData['schedule_array'];
        $currentSchedule['schedule_array_2'] = $this->viewData['schedule_array_2'];
        $currentSchedule['runnerArray'] = $this->viewData['runnerArray'];
        $currentSchedule['mezzanineArray'] = $this->viewData['mezzanineArray'];

        $currentSchedule['empLabelers'] = $empLabelers;
        $currentSchedule['empStockers'] = $empStockers;
        $currentSchedule['empIcers'] = $empIcers;
        $currentSchedule['empRunners'] = $empRunners;
        $currentSchedule['empMezzanines'] = $empMezzanines;

        $currentSchedule['employees'] = $empList;

        $empNonLabelers = array_diff($empList, $empLabelers);
        $empNonStockers = array_diff($empList, $empStockers);
        $empNonIcers = array_diff($empList, $empIcers);
        $empNonRunners = array_diff($empList, $empRunners);
        $empNonMezzanines = array_diff($empList, $empMezzanines);

        $currentSchedule['empNonLabelers'] = $empNonLabelers;
        $currentSchedule['empNonStockers'] = $empNonStockers;
        $currentSchedule['empNonIcers'] = $empNonIcers;
        $currentSchedule['empNonRunners'] = $empNonRunners;
        $currentSchedule['empNonMezzanines'] = $empNonMezzanines;

        $currentSchedule['id'] = $id;

        return view ('schedule.edit', $currentSchedule);
    }

    public function update($id, Request $request) {

        //Get the current schedule from the database
        $scheduler = Schedule::find($id);
        $this->viewData = json_decode($scheduler->schedule, true);
        $schedule_array = $this->viewData['schedule_array'];
        $schedule_array_2 = $this->viewData['schedule_array_2'];
        $runnerArray = $this->viewData['runnerArray'];
        $mezzanineArray = $this->viewData['mezzanineArray'];

        $labeler_ConveyorLine = $request['labeler_conveyor'];
        $labeler_SupportLine = $request['labeler_support'];
        $stocker_SupportLine = $request['stocker_support'];
        $mezzanine = $request['mezzanine'];
        $runner = $request['runner'];
        $icer_Conveyor = $request['icer_conveyor'];
        $icer_Support = $request['icer_support'];

        //Validation check -- do Validation for Icer, Do Validation across updated Schedule

        for($i = 0; $i < sizeof($labeler_ConveyorLine); $i++) {
            if(!empty($labeler_ConveyorLine[$i])) {
                $schedule_array[$i]['labeler'] = $labeler_ConveyorLine[$i];
            }
        }

        $msg = '';
        $fieldToCompare = 'labeler';
        $duplicate = $this->checkArrayDuplicate($schedule_array, $fieldToCompare);
        if($duplicate) {
            $msg = "Conveyor Lines cannot have same Labeler in multiple lines\n";
        }

        for($i = 0; $i < sizeof($labeler_SupportLine); $i++) {
            if(!empty($labeler_SupportLine[$i])) {
                $schedule_array_2[$i]['labeler'] = $labeler_SupportLine[$i];
            }
        }

        $duplicate = $this->checkArrayDuplicate($schedule_array_2, $fieldToCompare);
        if($duplicate) {
            $msg .= "Support Lines cannot have same Labeler in multiple lines\n";
        }


        for($i = 0; $i < sizeof($stocker_SupportLine); $i++) {
            if(!empty($stocker_SupportLine[$i])) {
                $schedule_array_2[$i]['stocker'] = $stocker_SupportLine[$i];
            }
        }

        $fieldToCompare = 'stocker';
        $duplicate = $this->checkArrayDuplicate($schedule_array_2, $fieldToCompare);
        if($duplicate) {
            $msg .= "Support Lines cannot have same Stocker in multiple lines\n";
        }


        for($i = 0; $i < sizeof($mezzanine); $i++) {
            if(!empty($mezzanine[$i])) {
                $mezzanineArray[$i]['name'] = $mezzanine[$i];
            }
        }

        $fieldToCompare = 'name';
        $duplicate = $this->checkArrayDuplicate($mezzanineArray, $fieldToCompare);
        if($duplicate) {
            $msg .= "Mezzanine cannot be same for multiple set of lines\n";
        }

        for($i = 0; $i < sizeof($runner); $i++) {
            if(!empty($runner[$i])) {
                $runnerArray[$i]['name'] = $runner[$i];
            }
        }
        $duplicate = $this->checkArrayDuplicate($runnerArray, $fieldToCompare);
        if($duplicate) {
            $msg .= "Runner cannot be same for multiple set of lines\n";
        }

        for($i = 0; $i < sizeof($icer_Conveyor); $i++) {
            if(!empty($icer_Conveyor[$i])) {
                $schedule_array[$i]['icer'] = $icer_Conveyor[$i];
            }
        }

        for($i = 0; $i < sizeof($icer_Support); $i++) {
            if(!empty($icer_Support[$i])) {
                $schedule_array_2[$i]['icer'] = $icer_Support[$i];
            }
        }

        //Do - Validation for ICER

        if(!empty($msg)) {
            Session::flash('message', $msg);
            return Redirect::back();
        }



        $this->viewData['schedule_array'] = $schedule_array;
        $this->viewData['schedule_array_2'] = $schedule_array_2;
        $this->viewData['runnerArray'] = $runnerArray;
        $this->viewData['mezzanineArray'] = $mezzanineArray;
        $this->viewData['id'] = $id;
        $scheduler->schedule = json_encode($this->viewData);
        $scheduler->update();


        return view ('schedule.generate', $this->viewData);

    }

    public function checkArrayDuplicate ($array, $field) {

        $duplicate = false;

        for($e = 0; $e < count($array); $e++)
        {
            for ($ee = $e+1; $ee < count($array); $ee++)
            {
                if (strcmp($array[$ee][$field],$array[$e][$field]) === 0)
                {
                    $duplicate = true;
                    break;
                }
            }
        }
        return $duplicate;
    }
}
