<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RadiusController extends Controller
{

    /**
     * Get all users from the radius database
     *
     * @param Request $request
     * @return void
     */

    public function getAllUsers() 
    {
        try {

            $radreply_data = DB::connection('radius')
                ->table('radreply')
                ->select('id', 'username', 'attribute', 'value')
                ->orderBy('id', 'asc')
                ->get();
    
            $radcheck_data = DB::connection('radius')
                ->table('radcheck')
                ->select('username', 'value')
                ->orderBy('id', 'asc')
                ->get();
    
            $mergedUsers = [];
    
            foreach ($radreply_data as $user) {
                $username = $user->username;
    
                if (!isset($mergedUsers[$username])) {
                    $mergedUsers[$username] = [
                        'Id' => $user->id,
                        'Username' => $username,
                        'Password' => '',
                    ];
                }
    
                $attributes = explode(',', $user->attribute);
                $values = explode(',', $user->value);
    
                foreach ($attributes as $index => $attribute) {
                    $attribute = trim($attribute);
                    $value = trim($values[$index]);
    
                    $mergedUsers[$username][$attribute] = $value;
                }
            }
    
            foreach ($radcheck_data as $user) {
                $username = $user->username;
    
                if (isset($mergedUsers[$username])) {
                    $mergedUsers[$username]['Password'] = $user->value;
                }
            }
    
            $data_radius = array_values($mergedUsers);
    
            $response = [
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'radius_users_quantity' => count($data_radius),
                    'radius_users_data' => $data_radius,
                ],
            ];
    
            return response()->json($response, $response['code']);
        } catch (\Exception $e) {
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'An error occurred while retrieving user data.',
                'error' => $e->getMessage(),
            ];
    
            return response()->json($response, $response['code']);
        }
    }


    /**
     * Get a user from the radius database
     *
     * @param Request $request
     * @return void
     */

    public function getUser(Request $request)
    {
        try {
            $username = $request->input('username');

            $radreply_framed = $this->getRadreplyFramedData($username);
            $radreply_ratelimit = $this->getRadreplyMikrotikData($username);
            $radcheck_creds = $this->getRadcheckData($username);


            $data_radius = [
                'Id' => $radreply_framed[0]->id,
                'Username' => $radreply_framed[0]->username,
                'Password' => $radcheck_creds[0]->value,
                'Mikrotik-Rate-Limit' => $radreply_ratelimit[0]->value,
                'Framed-Ip-Address' => $radreply_framed[0]->value,
            ];

            return $data_radius;

            $response = [
                'status' => 'success',
                'code' => 200,
                'data' => [
                'radius_user_quantity' => 1,
                'radius_user_data' => $data_radius,
                ],
            ];

            return response()->json($response, $response['code']);
        
        } catch (NotFoundHttpException $e) {
            $response = [
                'status' => 'error',
                'code' => 404,
                'message' => 'User does not exist.',
                'error' => $e->getMessage(),
            ];

        } catch (\Exception $e) {
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'An error occurred while retrieving user data.',
                'error' => $e->getMessage(),
            ];
        }

        return response()->json($response, $response['code']);
    }

    private function getRadreplyFramedData($username)
    {
        $radius_data = DB::connection('radius')
            ->table('radreply')
            ->select('*')
            ->where('username', '=', $username)
            ->where('attribute', '=', 'Framed-IP-Address')
            ->orderBy('id')
            ->get();

        if ($radius_data->isEmpty()) {
            throw new NotFoundHttpException();
        }

        return $radius_data;
    }

    private function getRadreplyMikrotikData($username)
    {
        $radius_data = DB::connection('radius')
            ->table('radreply')
            ->select('*')
            ->where('username', $username)
            ->where('attribute', 'Mikrotik-Rate-Limit')
            ->get();


        if ($radius_data->isEmpty()) {
            throw new NotFoundHttpException();
        }

        return $radius_data;
    }

    private function getRadcheckData($username)
    {
        $radcheck_data = DB::connection('radius')
            ->table('radcheck')
            ->select('*')
            ->where('username', $username)
            ->orderBy('id', 'asc')
            ->limit('')
            ->get();

        if ($radcheck_data->isEmpty()) {
            throw new NotFoundHttpException();
        }

        return $radcheck_data;
    }

    /**
     * Create a new user in the radius database
     *
     * @param Request $request
     * @return void
     */

    public function createUser (Request $request)
    {
        try {
            $data = $request->input();
    
            $username = strtolower($data['username']);
            $name = ucwords(strtolower($data['name']));
            $node = strtoupper($data['node']);
            $bandwidth = $data['bandwidth_plan'];
            $password = substr(md5($data['username']), 0, 8);
            $ip = $data['main_ip'];
    
            // Check if user already exists
            $existingUser = DB::connection('radius')->table('userinfo')
                ->where('username', $username)
                ->first();
    
            if ($existingUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User already exists.',
                ], 409);
            }
    
            DB::beginTransaction();
    
            DB::connection('radius')->table('radreply')->insert([
                'username' => $username,
                'attribute' => 'Mikrotik-Rate-Limit',
                'op' => '=',
                'value' => $bandwidth,
                'ip_old' => '',
                'nodo' => $node,
                'nodo_old' => '',
            ]);
    
            DB::connection('radius')->table('radreply')->insert([
                'username' => $username,
                'attribute' => 'Framed-IP-Address',
                'op' => '=',
                'value' => $ip,
                'ip_old' => '',
                'nodo' => $node,
                'nodo_old' => '',
            ]);
    
            DB::connection('radius')->table('radcheck')->insert([
                'username' => $username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $password,
            ]);
    
            DB::connection('radius')->table('userinfo')->insert([
                'username' => $username,
                'firstname' => $name,
                'creationdate' => date('Y-m-d H:i:s'),
                'creationby' => 'Babel',
                'updatedate' => date('Y-m-d H:i:s'),
            ]);
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'An error occurred while creating the user.',
                'error' => $e->getMessage(),
            ];
            
            return response()->json($response, $response['code']);
        }
    }

    /**
     * Update a user in the radius database
     *
     * @param Request $request
     * @return void
     */    

    public function updateUser (Request $request)
    {
        try {
            $data = $request->input();
    
            $username = strtolower($data['username']);
            $name = ucwords(strtolower($data['name']));
            $node = strtoupper($data['node']);
            $bandwidth = $data['bandwidth_plan'];
            $password = substr(md5($data['username']), 0, 8);
            $ip = $data['main_ip'];
    
            // Check if the user exists
            $userExists = DB::connection('radius')
                ->table('radcheck')
                ->where('username', $username)
                ->exists();
    
            if (!$userExists) {
                $response = [
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'User does not exist.',
                ];
    
                return response()->json($response, $response['code']);
            }
    
            // Update Mikrotik-Rate-Limit in radreply table
            DB::connection('radius')
                ->table('radreply')
                ->where('username', $username)
                ->where('attribute', 'Mikrotik-Rate-Limit')
                ->update([
                    'value' => $bandwidth,
                ]);
    
            // Update Framed-Ip-Address in radreply table
            DB::connection('radius')
                ->table('radreply')
                ->where('username', $username)
                ->where('attribute', 'Framed-Ip-Address')
                ->update([
                    'value' => $ip,
                ]);
    
            // Update username in userinfo table
            DB::connection('radius')
                ->table('userinfo')
                ->where('username', $username)
                ->update([
                    'updatedate' => date('Y-m-d H:i:s'),
                ]);
    
            $new_data = $this->getUser($request);
    
            $response = [
                'status' => 'success',
                'code' => 200,
                'message' => 'Bandwidth updated successfully.',
                'new_data' => $new_data,
            ];
    
            return response()->json($response, $response['code']);
        } catch (\Exception $e) {
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'An error occurred while updating the username.',
                'error' => $e->getMessage(),
            ];
    
            return response()->json($response, $response['code']);
        }
    }

    /**
     * Delete a user from the radius database.
     *
     * @param Request $request
     * @return void
     */
    
    public function deleteUser(Request $request)
    {
        try {
            $data = $request->input();
            $username = $data['username'];
            
            // Check if the user exists
            $userExists = DB::connection('radius')
                ->table('radcheck')
                ->where('username', $username)
                ->exists();

            if (!$userExists) {
                $response = [
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'User does not exist.',
                ];

                return response()->json($response, $response['code']);
            }

            // Delete user from radcheck table
            DB::connection('radius')
                ->table('radcheck')
                ->where('username', $username)
                ->delete();

            // Delete user from radreply table
            DB::connection('radius')
                ->table('radreply')
                ->where('username', $username)
                ->delete();

            // Delete user from userinfo table
            DB::connection('radius')
                ->table('userinfo')
                ->where('username', $username)
                ->delete();

            $response = [
                'status' => 'success',
                'code' => 200,
                'message' => 'User deleted successfully.',
            ];

            return response()->json($response, $response['code']);
        } catch (\Exception $e) {
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'An error occurred while deleting the user.',
                'error' => $e->getMessage(),
            ];
            
            return response()->json($response, $response['code']);
            #throw new HttpException($response['code'], $response['message']);
        }
    }

}

        // public function updateUser (Request $request)
        // {
        //     try {
        //         $data = $request->input();
        
        //         $username = strtolower($data['username']);
        //         $name = ucwords(strtolower($data['name']));
        //         $node = strtoupper($data['node']);
        //         $bandwidth = $data['bandwidth_plan'];
        //         $password = substr(md5($data['username']), 0, 8);
        //         $ip = $data['main_ip'];
    
    
    
        //         // Check if the user exists
        //         $userExists = DB::connection('radius')
        //             ->table('radcheck')
        //             ->where('username', $old_username)
        //             ->exists();
        
        //         if (!$userExists) {
        //             $response = [
        //                 'status' => 'error',
        //                 'code' => 404,
        //                 'message' => 'User does not exist.',
        //             ];
        
        //             return response()->json($response, $response['code']);
        //         }
    
            
        //         // Update username in radcheck table
        //         DB::connection('radius')
        //             ->table('radcheck')
        //             ->where('username', $old_username)
        //             ->update([
        //                 'username' => $new_username,
        //             ]);
        
        //         // Update username in radreply table
        //         DB::connection('radius')
        //             ->table('radreply')
        //             ->where('username', $old_username)
        //             ->update([
        //                 'username' => $new_username,
        //             ]);
        
        //         // Update Mikrotik-Rate-Limit in radreply table
        //         DB::connection('radius')
        //             ->table('radreply')
        //             ->where('username', $new_username)
        //             ->where('attribute', 'Mikrotik-Rate-Limit')
        //             ->update([
        //                 'value' => $new_bandwidth,
        //             ]);
        
        //         // Update Framed-Ip-Address in radreply table
        //         DB::connection('radius')
        //             ->table('radreply')
        //             ->where('username', $new_username)
        //             ->where('attribute', 'Framed-Ip-Address')
        //             ->update([
        //                 'value' => $new_ip,
        //             ]);
        
        //         // Update username in userinfo table
        //         DB::connection('radius')
        //             ->table('userinfo')
        //             ->where('username', $old_username)
        //             ->update([
        //                 'username' => $new_username,
        //                 'updateddate' => date('Y-m-d H:i:s'),
        //             ]);
        
        //         $response = [
        //             'status' => 'success',
        //             'code' => 200,
        //             'message' => 'Username updated successfully.',
        //         ];
        
        //         return response()->json($response, $response['code']);
    
        //     } catch (\Exception $e) {
        //         $response = [
        //             'status' => 'error',
        //             'code' => 500,
        //             'message' => 'An error occurred while updating the username.',
        //             'error' => $e->getMessage(),
        //         ];
        
        //         return response()->json($response, $response['code']);
        //     }
        