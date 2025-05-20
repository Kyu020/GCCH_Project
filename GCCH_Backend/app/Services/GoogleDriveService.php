<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\StorageAttributes;
use League\Flysystem\FileAttributes;
use Illuminate\Support\Str;

class GoogleDriveService
{
    public function uploadFile($file, $customFileName){
        $folderPath = env('GOOGLE_DRIVE_FOLDER_PATH');
        $extension = $file->getClientOriginalExtension();
        $uniqueSuffix = now()->timestamp . '_' . Str::random(8);
        $fileName = $customFileName . '_' .$uniqueSuffix . '.' . $extension;

        $path = Storage::disk('google')->putFileAs(
            $folderPath,
            $file,
            $fileName   
        );

        \Log::info('Uploaded path:', ['path' => $path]);

        if($path){
            $contents = Storage::disk('google')->listContents($folderPath, false);

            \Log::info('Drive folder contents:', ['contents' => $contents]);

            foreach ($contents as $content) {
                if($content instanceof FileAttributes && basename($content->path()) === $fileName) {

                    $fileId = $content->extraMetaData()['id'] ?? null;
                    
                    //if ($fileId) {
                    //    $this->setPublicPermission($fileId);
                    //}
                    
                    return [
                        'name' => basename($content->path()),
                        'path' => $content->path(),
                        'file_id' => $fileId,
                    ];
                }
            }
        }

        return null;
    }
    
    /*
    public function setPublicPermission($fileId){

        $client = new \Google_Client();
        $client->setAuthConfig(storage_path('app/google/credentials.json')); // Adjust path if needed
        $client->addScope(\Google_Service_Drive::DRIVE);

        $service = new \Google_Service_Drive($client);

        $permission = new \Google_Service_Drive_Permission();
        $permission->setType('anyone');
        $permission->setRole('reader');

        try {
            $service->permissions->create($fileId, $permission);
            \Log::info('Permission set to public for file: ' . $fileId);
        } catch (\Exception $e) {
            \Log::error('Failed to set permission: ' . $e->getMessage());
        }
    }
    */

    public function listFiles(){
        return Storage::disk('google')->listContents('/', false);
    }

    public function downloadFile($filePath){
        return Storage::disk('google')->get($filePath);
    }

    public function deleteFile($filePath){
        return Storage::disk('google')->delete($filePath);
    }

    public function getFileEmbedUrl($fileId){
        return "https://drive.google.com/file/d/{$fileId}/preview";
    }

    public function getFileMetaData($fileId){
        $client = new \Google_Client();
        $client->setAccessToken(session('google_token'));

        $driveService = new \Google_Service_Drive($client);

        try {
            return $driveService->files->get($fileId);
        } catch (\Exception $e) {
            \Log::error('Error retrieving file metadata from Google Drive', ['error' => $e->getMessage()]);
            return null;
        }
    }
}