<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('admin.students.index', [
            'students' => Student::query()
                ->withCount('classrooms')
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(15),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.students.create', [
            'classrooms' => Classroom::query()->orderBy('code')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = Student::create($request->safe()->except('classroom_ids'));
        $student->classrooms()->sync($request->validated('classroom_ids', []));

        return redirect()->route('admin.students.index')->with('status', 'Data mahasiswa berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student): RedirectResponse
    {
        return redirect()->route('admin.students.edit', $student);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student): View
    {
        return view('admin.students.edit', [
            'student' => $student->load('classrooms:id'),
            'classrooms' => Classroom::query()->orderBy('code')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->safe()->except('classroom_ids'));
        $student->classrooms()->sync($request->validated('classroom_ids', []));

        return redirect()->route('admin.students.index')->with('status', 'Data mahasiswa berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('admin.students.index')->with('status', 'Data mahasiswa berhasil dihapus.');
    }
}
