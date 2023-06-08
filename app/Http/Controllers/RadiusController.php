<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Validator;
use Knuckles\Scribe\Attributes\Group;


#[Group("Radius Controller", "API for managing RADIUS")]

class RadiusController extends Controller
{

    /**
     * Get all users from the radius database
     *
     * @return Illuminate\Http\Response
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
    
            $userinfo_data = DB::connection('radius')
                ->table('userinfo')
                ->select('username', 'firstname', 'lastname', 'nodo', 'creationdate', 'updatedate')
                ->orderBy('id', 'asc')
                ->get();
    
            $merged_data = [];
    
            foreach ($radreply_data as $user) {
                $username = $user->username;
    
                if (!isset($merged_data[$username])) {
                    $merged_data[$username] = [
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
    
                    $merged_data[$username][$attribute] = $value;
                }
            }
    
            foreach ($radcheck_data as $user) {
                $username = $user->username;
    
                if (isset($merged_data[$username])) {
                    $merged_data[$username]['Password'] = $user->value;
                }
            }
    
            foreach ($userinfo_data as $user) {
                $username = $user->username;
                $created_at = explode(' ', $user->creationdate);
                $updated_at = explode(' ', $user->updatedate);
    
                if (isset($merged_data[$username])) {
                    $merged_data[$username]['Name'] = $user->firstname . $user->lastname;
                    $merged_data[$username]['Node'] = $user->nodo;
                    $merged_data[$username]['Creation Date'] = $created_at[0];
                    $merged_data[$username]['Update Date'] = $updated_at[0];
                }
            }
    
            $data_radius = array_values($merged_data);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Users found',
                'detail' => [
                    'radius_users_quantity' => count($data_radius),
                    'radius_users_data' => $data_radius
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            $errorMessage = 'Error retrieving users';
    
            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a user from the radius database based on the username in the request
     *
     * @param Request $request 
     * @return Illuminate\Http\Response
     */

    public function getUser (Request $request)
    {
        try {
            $validate = $this->validateUser($request);
            
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error getting user',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else {

                $data = $request->input();
                $username = $data['username'];

                // Check if the user exists
                $userExists = DB::connection('radius')
                    ->table('radcheck')
                    ->where('username', $username)
                    ->exists();
    
                if (!$userExists) {
                
                    return response()->json([
                        'status' => 'error',
                        'message' => 'User does not exist.',
                    ], Response::HTTP_NOT_FOUND);

                } else {
    
                    $radreply_framed = $this->getUserFramedIP($username);
                    $radreply_ratelimit = $this->getUserMikrotikRateLimit($username);
                    $radcheck_creds = $this->getUserPassword($username);
                    $userinfo_data = $this->getUserInfo($username); 
                    $created_at = explode(' ', $userinfo_data[0]->creationdate);
                    $update_at = explode(' ', $userinfo_data[0]->updatedate);

                    $data_radius = [
                        'id' => $radreply_framed[0]->id,
                        'name' => $userinfo_data[0]->firstname . $userinfo_data[0]->lastname,
                        'username' => $radreply_framed[0]->username,
                        'password' => $radcheck_creds[0]->value,
                        'bandwith_plan' => $radreply_ratelimit[0]->value,
                        'main_ip' => $radreply_framed[0]->value,
                        'node' => $userinfo_data[0]->nodo,
                        'created_at' => $created_at[0],
                        'updated_at' => $update_at[0],
                    ];
            
                    return response()->json([
                        'status' => 'success',
                        'message' => 'User found',
                        'detail' => $data_radius,
                    ], Response::HTTP_OK);
                };
            };
    
            } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving user',
                'detail' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

        /* 
        * Get the user's Framed-Ip-Address from the radius database based on the username
        *
        * @param str $username 
        * @return array $radreply_data
        */

        private function getUserFramedIP ($username)
        {
            $radreply_data = DB::connection('radius')
                ->table('radreply')
                ->select('*')
                ->where('username', '=', $username)
                ->where('attribute', '=', 'Framed-IP-Address')
                ->orderBy('id')
                ->get();

            if ($radreply_data->isEmpty()) {
                throw new NotFoundHttpException();
            }

            return $radreply_data;
        }

        /* 
        * Get the user's Mikrotik-Rate-Limit from the radius database based on the username
        *
        * @param str $username 
        * @return array $radius_data
        */

        private function getUserMikrotikRateLimit ($username)
        {
            $radreply_data = DB::connection('radius')
                ->table('radreply')
                ->select('*')
                ->where('username', $username)
                ->where('attribute', 'Mikrotik-Rate-Limit')
                ->get();


            if ($radreply_data->isEmpty()) {
                throw new NotFoundHttpException();
            }

            return $radreply_data;
        }

        /* 
        * Get the user's password from the radius database based on the username
        *
        * @param str $username
        * @return array $radcheck_data
        */

        private function getUserPassword ($username)
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

        /* 
        * Get the user's name from the radius database based on the username
        *
        * @param str $username
        * @return array $userinfo_data
        */


        private function getUserInfo ($username)
        {
            $userinfo_data = DB::connection('radius')
                ->table('userinfo')
                ->select('*')
                ->where('username', $username)
                ->get();
            
            if ($userinfo_data->isEmpty()) {
                throw new NotFoundHttpException();
            }

            return $userinfo_data;
        }

    /**
     * Create a new user in the radius database
     *
     * @param Request $request
     *  @param string $username
     *  @param string $name
     *  @param string $bandwidth_plan
     *  @param string $node
     *  @param string $main_ip
     * @return Illuminate\Http\Response
     */

    public function createUser (Request $request)
    {
        try {

            $validate = $this->validateRequest($request);
            
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error creating user',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else {
                $data = $request->input();
                $username = strtolower($data['username']);
                $name = ucwords(strtolower($data['name']));
                $node = strtoupper($data['node']);
                $bandwidth = $data['bandwidth_plan'];
                $ip = $data['main_ip'];
                $password = substr(md5($data['username']), 0, 8);

                // Check if user already exists
                $existingUser = DB::connection('radius')->table('userinfo')
                    ->where('username', $username)
                    ->first();
        
                if ($existingUser) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'User already exists.',
                    ], Response::HTTP_CONFLICT);
                }

                // Create user in radius database
        
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
                    'nodo' => $node,
                    'creationdate' => date('Y-m-d H:i:s'),
                    'creationby' => 'Babel',
                    'updatedate' => date('Y-m-d H:i:s'),
                ]);

                DB::commit();
        
                return response()->json([
                    'status' => 'success',
                    'message' => 'User created successfully.',
                ], Response::HTTP_CREATED);

            }

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while creating the user.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
         * Validate the input data
         * 
         * @param Request $request
         * @return array $response
         */

        private function validateRequest (Request $request)
        {
            $data = $request->input();

            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
                'name' => 'required|string',
                'bandwidth_plan' => 'required|string',
                'node' => 'required|string',
                'main_ip' => 'required|ip',
            ]);
        
            if ($validator->fails()) {

                $response = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Invalid data.',
                    'detail' => $validator->errors(),
                ];

            } else {
                $response = [
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'Data is valid.',
                ];
            }

            return $response;

        }

        /**
         * Validate the username
         * 
         * @param Request $request
         * @return array $response
         */

        private function validateUser (Request $request)
        {
            $data = $request->input();

            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
            ]);
        
            if ($validator->fails()) {

                $response = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Invalid data.',
                    'detail' => $validator->errors(),
                ];

            } else {
                $response = [
                    'status' => 'success',
                    'code' => 200,
                    'message' => 'Data is valid.',
                ];
            }

            return $response;
        }

    /**
     * Update user paramas in the radius database
     *
     * @param Request $request
     *  @param string $username
     *  @param string $name
     *  @param string $bandwidth_plan
     *  @param string $node
     *  @param string $main_ip
     * @return Illuminate\Http\Response
     */ 

    public function updateUser(Request $request)
    {
        try {
            $validate = $this->validateRequest($request);
            
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error updating user',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else {
                $data = $request->input();
                $username = strtolower($data['username']);
                $name = ucwords(strtolower($data['name']));
                $node = strtoupper($data['node']);
                $bandwidth = $data['bandwidth_plan'];
                $ip = $data['main_ip'];
    
                // Check if the user exists
                $userExists = DB::connection('radius')
                    ->table('radcheck')
                    ->where('username', $username)
                    ->exists();
        
                if (!$userExists) {
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'User does not exist.',
                    ], Response::HTTP_NOT_FOUND);

                } else {
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

                    // Update userinfo table
                    DB::connection('radius')
                        ->table('userinfo')
                        ->where('username', $username)
                        ->update([
                            'firstname' => $name,
                            'nodo' => $node,
                            'updatedate' => date('Y-m-d H:i:s'),
                        ]);

                    
                    $new_data = $this->getUser($request);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Bandwidth updated successfully',
                        'detail' => $new_data->original,
                    ], Response::HTTP_OK);
                }
            }
    
        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while updating the user.',
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a user from the radius database.
     *
     * @param Request $request
     * @param string $username
     * @return Illuminate\Http\Response
     */
    
    public function deleteUser(Request $request)
    {
        try {
            $validate = $this->validateUser($request);
            
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error deleting user',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else {

                $data = $request->input();
                $username = $data['username'];
                
                // Check if the user exists
                $userExists = DB::connection('radius')
                    ->table('radcheck')
                    ->where('username', $username)
                    ->exists();

                if (!$userExists) {

                    return response()->json([
                        'status' => 'error',
                        'message' => 'User does not exist.',
                    ], Response::HTTP_NOT_FOUND);

                } else {
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

                    return response()->json([
                        'status' => 'success',
                        'message' => 'User deleted successfully',
                    ], Response::HTTP_OK);
                }
            }

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting user',
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
 
        }
    }

}
