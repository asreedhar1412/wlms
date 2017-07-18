<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Employee;
use App\Http\Requests;
use Illuminate\Foundation\Validation\ValidatesRequests;
//use Illuminate\Contracts\Validation\Validator;
use Validator;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }


    
    public function index()
    {

        $employees = Employee::all();
       return view('addemployee.index', compact('employees'));
    }

    public function show($id)
    {
        $employee = Employee::findOrFail($id);
        return view('addemployee.show', compact('employee'));

    }

    public function create()
    {
        return view('addemployee.create');
    }

    public function store(Request $request)
    {

     $validator = Validator::make($request->all(), [
         'empid' => 'bail|required|numeric|unique:employees',
         'empfname' => 'required|max:255',
         'emplname' => 'required|max:255',
         'positiontype' => 'required',
         'experience' => 'required',
         'english'=>'required_without_all: spanish, other',
         'spanish'=>'required_without_all: english, other',
         'other'=>'required_without_all: english, spanish',
         'labeler_rating' => 'required',
         'stocker_rating' => 'required',

     ]);

        if ($validator->fails()) {
            return redirect('/addemployee/create')->withErrors($validator)->withInput();
        }

        $employee = new Employee($request->all());
        $employee->save();
        return redirect('/addemployee');

    }
        public
        function edit($id)
        {
            
            $employee = Employee::find($id);
            return view('addemployee.edit', compact('employee'));
        }

        public
        function update($id, Request $request)
        {
            //
            $employee = new Employee($request->all());
            $employee = Employee::find($id);
            $employee->update($request->all());
            return redirect('/addemployee');
        }

        public
        function destroy($id)
        {
            Employee::find($id)->delete();
            return redirect('/addemployee');
        }


    
public function scopeSearchByKeyword($query, $keyword)
{
    if ($keyword!='') {
        $query->where(function ($query) use ($keyword) {
            $query->where("empid", "LIKE","%$keyword%")
                ->orWhere("empfname", "LIKE", "%$keyword%")
                ->orWhere("emplname", "LIKE", "%$keyword%")
                ->orWhere("email", "LIKE", "%$keyword%")
                ->orWhere("phone", "LIKE", "%$keyword%");
        });
    }
    return $query;
}

    }

