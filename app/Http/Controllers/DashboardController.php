<?php

namespace App\Http\Controllers;

use App\Models\MusicLesson;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        switch ($user->role) {
            case 'admin':
                $data = $this->getAdminDashboardData();
                break;
            case 'teacher':
                $data = $this->getTeacherDashboardData($user);
                break;
            case 'guardian':
                $data = $this->getGuardianDashboardData($user);
                break;
            default:
                $data = $this->getStudentDashboardData($user);
                break;
        }

        $data['userRole'] = $user->role;

        return view('ingelogd.dashboard', compact('data'));
    }

    private function getAdminDashboardData()
    {
        $totalUsers = User::count();

        $lessonsGiven = MusicLesson::where('date', '<', Carbon::now())
            ->count();

        $recentLessons = MusicLesson::with(['student', 'teacher'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($lesson) {
                return [
                    'title' => $lesson->title ?? 'Untitled Lesson',
                    'student_name' => optional($lesson->student)->user->name ?? 'Unknown Student',
                    'teacher_name' => optional($lesson->teacher)->user->name ?? 'Unknown Teacher',
                    'is_proefles' => $lesson->is_proefles ? 'Yes' : 'No',
                    'created_at' => $lesson->created_at->diffForHumans()
                ];
            });

        return [
            'totalUsers' => $totalUsers,
            'lessonsGiven' => $lessonsGiven,
            'recentLessons' => $recentLessons,
            'role' => 'admin'
        ];
    }

    private function getTeacherDashboardData($user)
    {
        $teacher = Teacher::where('user_id', $user->id)->first();

        $upcomingLessons = MusicLesson::where('teacher_id', $teacher->id)
            ->where('date', '>=', Carbon::now())
            ->count();  

        $studentCount = Student::whereHas('musicLessons', function ($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })->count();

        $lessonsCount = MusicLesson::where('teacher_id', $teacher->id)->count();

        $lessons = MusicLesson::with('student.user')
            ->where('teacher_id', $teacher->id)
            ->where('date', '>=', Carbon::now())
            ->orderBy('date')
            ->take(5)
            ->get()
            ->map(function ($lesson) {
                return [
                    'title' => $lesson->title,
                    'date' => Carbon::parse($lesson->date)->format('Y-m-d'),
                    'student_name' => ($lesson->student->user->name) ?? 'Unknown Student'
                ];
            });     

        return [
            'upcomingLessons' => $upcomingLessons,
            'studentCount' => $studentCount,
            'lessonsCount' => $lessonsCount,
            'lessons' => $lessons,
            'role' => 'teacher'
        ];
    }

    private function getGuardianDashboardData($user)
    {
        $guardian = $user->guardian;
        $childrenIds = Student::where('guardian_id', $guardian->id)
            ->pluck('id');
            
        $totalChildrenLessons = MusicLesson::whereIn('student_id', $childrenIds)
            ->count();

        $childrenCount = $childrenIds->count();

        $lessons = MusicLesson::with(['student.user', 'teacher.user'])
            ->whereIn('student_id', $childrenIds)
            ->orderBy('date', 'desc')
            ->take(5)
            ->get()
            ->map(function ($lesson) {
                return (object)[
                    'title' => $lesson->title ?? 'Untitled Lesson',
                    'student_name' => optional($lesson->student->user)->name ?? 'Unknown Student',
                    'teacher_name' => optional($lesson->teacher->user)->name ?? 'Unknown Teacher',
                    'status' => Carbon::parse($lesson->date)->isPast() ? 'completed' : 'upcoming',
                    'comment' => $lesson->comments ?? '',
                    'created_at' => $lesson->created_at,
                    'date' => $lesson->date
                ];
            });

        return [
            'totalChildrenLessons' => $totalChildrenLessons,
            'childrenCount' => $childrenCount,
            'childrenLessons' => $lessons,
            'role' => 'guardian'
        ];
    }

    private function getStudentDashboardData($user)
    {
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return [
                'myLessons' => 0,
                'completedLessons' => 0,
            ];
        }

        $myLessons = MusicLesson::where('student_id', $student->id)
            ->where('date', '>=', Carbon::now())
            ->count();

        $completedLessons = MusicLesson::where('student_id', $student->id)
            ->where('date', '<', Carbon::now())
            ->count();

            $lessons = MusicLesson::with('student.user')
            ->where('student_id', $student->id)
            ->where('date', '>=', Carbon::now())
            ->orderBy('date')
            ->take(5)
            ->get()
            ->map(function ($lesson) {
                return [
                    'title' => $lesson->title,
                    'date' => Carbon::parse($lesson->date)->format('Y-m-d'),
                    'student_name' => ($lesson->student->user->name) ?? 'Unknown Student'
                ];
            });     

  

        return [
            'myLessons' => $myLessons,
            'completedLessons' => $completedLessons,
            'lessons' => $lessons
        ];
    }
}
