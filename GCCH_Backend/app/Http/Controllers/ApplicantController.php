<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Models\Applicant;
use App\Models\Job;
use App\Models\User;
use App\Models\Resume;
use App\Models\JobApplication;
use App\Services\GoogleDriveService;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Yaza\LaravelGoogleDriveStorage\GDrive;

class ApplicantController extends Controller
{
    
    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }
    

    public function index(){
        return view('dashboard.applicant-dashboard');
    }

    public function jobapply(Request $request){
        try{
            $user = Auth::user();

            $applicant = $user->applicant;
            if (!$applicant) {
                return response()->json(['error' => 'Applicant not found'], 404);
            }           

            $validated = $request->validate([
                'job_id' => 'required|exists:jobs,id',
                'resume' => 'nullable|file|mimes:pdf|max:2048',
                'cover_letter' => 'required|string|max:1000',
            ]);

            $existingApplication = JobApplication::where('applicant_id', $applicant->id)
                ->where('job_id', $validated['job_id'])
                ->first();

            if ($existingApplication) {
                return response()->json([
                    'error' => 'You have already applied for this job',
                    'status' => $existingApplication->status
                ], 422);
            }

            $jobApplication = new JobApplication();
            $jobApplication->job_id = $validated['job_id'];
            $jobApplication->applicant_id = $applicant->id;
            $jobApplication->cover_letter = $validated['cover_letter'];

            if($request->hasFile('resume')){
                $file = $request->file('resume');
                $customFileName = $applicant->first_name . '_' . $applicant->last_name . '_resume';
                $fileMeta = $this->googleDriveService->uploadFile($file, $customFileName);
                
                $resume = new Resume();
                $resume->applicant_id = $applicant->id;
                $resume->file_name = $fileMeta['name'];
                $resume->drive_file_id = $fileMeta['file_id'];
                $resume->mime_type = $file->getMimeType();
                $resume->save();

                $jobApplication->resume = $resume->id;
            }

            $jobApplication->save();

            //Notify the company
            $job = Job::find($validated['job_id']);
                if($job && $job->company_id){
                    $notificationController = new NotificationController();
                    $content = "New job application for " . $job->job_title . " from " . $applicant->first_name . " " . $applicant->last_name;
                    $notificationController->notifyUser($job->company_id, $content, 'job_application');
                }
            
            return response()->json([
                'message' => 'Job application submitted successfully',
                'fileMeta' => $fileMeta,
                'job_application' => $jobApplication
            ], 201);
        
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred while applying for the job'], 500);
        }
    }

    public function jobdisplay(){
        try{
            $user = Auth::user();

            $applicant = $user->applicant;

            if (!$applicant) {
                return response()->json(['error' => 'Applicant not found'], 404);
            }

            $course = $applicant->course;

            $matchedJobs = Job::where('recommended_course', $course)
                ->orWhere('recommended_course_2', $course)
                ->orWhere('recommended_course_3', $course)
                ->get();

            $matchedJobsIds = $matchedJobs->pluck('id');
            
            $otherJobs = Job::whereNotIn('id', $matchedJobsIds)
                ->get();

            return response()->json([
                'matchedjobs' => $matchedJobs,
                'otherjobs' => $otherJobs
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()], 422);
        }
    }

    public function applicationStatus(){
        try{
            $user = Auth::user();
            $applicant = $user->applicant;

            if (!$applicant) {
                return response()->json(['error' => 'Applicant not found'], 404);
            }

            $applications = JobApplication::where('applicant_id', $applicant->id)->with(['job'])->get();
            if ($applications->isEmpty()) {
                return response()->json(['message' => 'No applications found.'], 404);
            }
            $applicationsData = $applications->map(function ($application) {
                return [
                    'job_title' => $application->job->job_title,
                    'status' => $application->status,
                    'updated_at' => $application->created_at,
                ];
            });
            return response()->json(['applications' => $applicationsData], 200);  
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()], 422);
        }
    }
}
