<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Models\JobApplication;
use App\Services\GoogleDriveService;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class CompanyController extends Controller
{   
    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    public function index(){
        return view('dashboard.company-dashboard');
    }

    public function postjob(){
        try{
            $validated = request()->validate([
                'job_title' => 'required|string|max:255',
                'job_description' => 'required|string',
                'job_location' => 'required|string|max:255',
                'job_type' => 'required|in:full_time,part_time,contract,internship',
                'monthly_salary' => 'required|numeric|min:0',
                'recommended_course' => 'required|string|max:255',
                'recommended_course_2' => 'nullable|string|max:255',
                'recommended_course_3' => 'nullable|string|max:255',
            ]);

        $user = Auth::user();

        $company = $user->company;

        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }
        
        $job = Job::create([
            'company_id' => $company->id,
            'job_title' => $validated['job_title'],
            'job_description' => $validated['job_description'],
            'job_location' => $validated['job_location'],
            'job_type' => $validated['job_type'],
            'monthly_salary' => $validated['monthly_salary'],
            'recommended_course' => $validated['recommended_course'],
            'recommended_course_2' => $validated['recommended_course_2'],
            'recommended_course_3' => $validated['recommended_course_3'],
            'date_posted' => now(),
            'status' => 'open',
        ]);

        return response()->json(['message' => 'Job posted successfully', 'job' => $job], 201);

        }catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()], 422);
        }
    }

    public function jobdisplay(){
        try{
            $user = Auth::user();

            $company = $user->company;

            if (!$company) {
                return response()->json(['error' => 'Company not found'], 404);
            }
            
            $jobs = Job::where('company_id', $company->id)->get();

            if ($jobs->isEmpty()) {
                return response()->json(['message' => 'No jobs posted yet.'], 404);
            }

            return response()->json(['jobs' => $jobs], 200);

        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not retrieve jobs'], 500);
        }
    }

    public function viewJobApplications(Job $job){
        try{
            $user = Auth::user();
            $company = $user->company;

            if (!$company) {
                return response()->json(['error' => 'Company not found'], 404);
            }

            $applications = JobApplication::where('job_id', $job->id)->with(['applicant', 'resumeFile'])->get();

            if ($applications->isEmpty()) {
                return response()->json(['message' => 'No applications for this job.'], 404);
            }

            $applicationsData = $applications->map(function ($application){
                $embedUrl = null;
                $resumeFile = $application->resumeFile;

                if ($resumeFile) {
                    $embedUrl = $this->googleDriveService->getFileEmbedUrl($resumeFile->drive_file_id);
                }

                return [
                    'applicant' => $application->applicant,
                    'resume' => [
                        'file_name' => $resumeFile ? $resumeFile->file_name : null,
                        'embed_url' => $embedUrl,
                    ],
                    'cover_letter' => $application->cover_letter,
                    'status' => $application->status,
                    'date_applied' => $application->date_applied,
                ];
            });

            return response()->json(['applications' => $applicationsData], 200);

        }catch (ValidationException $e) {
            return response()->json(['error' => 'Could not retrieve applications'], 500);
        }
    }

    public function assessApplication(Request $request, $applicationId){
        try{

            $application = JobApplication::with('job')->find($applicationId);

            if(!$application){
                return response()->json(['error' => 'Application not found'], 404);
            }

            $validated = $request->validate([
                'status'=>'required|in:applied,interview,assessment,rejected,accepted', //required|in:applied,for_interview,ongoing_assessment,rejected,accepted
                'scheduled_at'=>'nullable|date_format:Y-m-d H:i:s',
                'comment'=>'nullable|string',
            ]);

            $user = Auth::user();
            $company = $user->company;


            if (!$company || $application->job->company_id !== $company->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            } 

            $application->status = $validated['status'];

            if ($validated['status'] == 'interview' || $validated['status'] == 'assessment') {
                if (isset($validated['scheduled_at'])) {
                    $application->scheduled_at = $validated['scheduled_at'];
                } else {
                    return response()->json(['error' => 'Scheduled date is required for interview or assessment'], 422);
                }
            } elseif ($validated['status'] == 'rejected' || $validated['status'] == 'accepted') {
                $application->scheduled_at = null; // Clear scheduled date if status is rejected or accepted
            }

            if(isset($validated['comment'])){
                $application->comment = $validated['comment'];
            }

            $application->save();

            // Notify the applicant about the status change
            $notifier = new NotificationController();
            $content = "Your application for " . $application->job->job_title . " has been updated";
            $notifier->notifyUser($application->applicant->user_id, $content, 'application_update');

            return response()->json([
                'message' => 'Application status updated successfully',
                'application' => $application
            ], 200);
        } catch (ValidationException $e){
            return response()->json(['error' => 'Could not update application status'], 422);
        }
    }
}
