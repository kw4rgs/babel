<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Validator;



/**
 * @group Radius Controller
 *
 * API for managing Radius Server
 */

class RadiusController extends Controller
{

    /**
     * Get All Users
     * 
     * This endpoint allows you to get all users from the radius database
     * 
     * @authenticated
     * 
     * @response 200 {"status":"success","message":"Users found","detail":{"id":138421,"name":"Kwargs","username":"1.1.1.1","password":"e08fsa13","bandwith_plan":"11440k/11440k","main_ip":"1.1.1.1","node":"ROSEDAL","created_at":"2023-06-07","updated_at":"2023-06-08"}}
     * @response 500 {"status": "error", "message": "Error retrieving users", "detail": "SQLSTATE[HY000] [2002] No such file or directory"}
     * 
    */

    /*
     * Get all users from the radius database
     *
     * @param Request $request
     * @return Illuminate\Http\Response
     */

    public function getAllUsers()
    {
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
            $username = isset($user->username) ? $user->username : null;
            $created_at = isset($user->creationdate) ? explode(' ', $user->creationdate) : null;
            $updated_at = isset($user->updatedate) ? explode(' ', $user->updatedate) : null;
    
            if (isset($merged_data[$username])) {
                try {
                    $merged_data[$username]['Name'] = isset($user->firstname) ? $user->firstname : null;
                } catch (Exception $e) {
                    return $e;
                }

                $merged_data[$username]['Node'] = isset($user->nodo) ? $user->nodo : null;
                $merged_data[$username]['Creation Date'] = isset($created_at[0]) ? $created_at[0] : null;
                $merged_data[$username]['Update Date'] = isset($updated_at[0]) ? $updated_at[0] : null;
            }
        }
    
        return $merged_data;
    }

    
    /**
     * Get User
     * 
     * This endpoint allows you to get a user from the radius database based on the username in the request
     * 
     * @authenticated
     * 
     * @bodyParam username string required The username of the user. Example: 1.1.1.1
     * 
     * @response 200 {"status":"success","message":"User found","detail":{"id":138421,"name":"Kwargs","username":"1.1.1.1","password":"e08fsa13","bandwith_plan":"11440k/11440k","main_ip":"1.1.1.1","node":"ROSEDAL","created_at":"2023-06-07","updated_at":"2023-06-08"}}
     * @response 400 {"status":"error","message":"Error getting user","detail":{"username":["The username field is required."]}}
     * @response 404 {"status":"error","message":"User does not exist."}
     * @response 500 {"status": "error", "message": "Error retrieving users", "detail": "SQLSTATE[HY000] [2002] No such file or directory"}
     * 
    */

    /*
     * Get all users from the radius database
     *
     * @param Request $request
     * @return Illuminate\Http\Response
     */

    /*
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
     * Create User
     * 
     * This endpoint allows you to create a user in the radius database.
     * 
     * @authenticated
     * 
     * @bodyParam name string required The name of the user. Example: Kwargs
     * @bodyParam username string required The username of the user. Example: 1.1.1.1
     * @bodyParam bandwith_plan string required The bandwith plan of the user. Example: 11440k/11440k
     * @bodyParam node string required The node of the user. Example: ROSEDAL
     * @bodyParam main_ip string required The main ip of the user. Example: 1.1.1.1 
     * 
     * @response 201 {"status":"success","message":"User created successfully."}
     * @response 400 {"status":"error","message":"Error creating user","detail":{"main_ip":["The main ip must be a valid IP address."]}}
     * @response 409 {"status":"error","message":"User already exists."}
     * @response 500 {"status": "error", "message": "An error occurred while creating the user."}
     * 
    */

    /*
     * Get all users from the radius database
     *
     * @param Request $request
     * @return Illuminate\Http\Response
     */

    /*
     * Get a user from the radius database based on the username in the request
     *
     * @param Request $request 
     * @return Illuminate\Http\Response
     */

    /*
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
     * Update User
     * 
     * This endpoint allows you to update a user in the radius database.
     * 
     * @authenticated
     * 
     * @bodyParam name string required The name of the user. Example: Kwargs
     * @bodyParam username string required The username of the user. Example: 1.1.1.1
     * @bodyParam bandwith_plan string required The bandwith plan of the user. Example: 11440k/11440k
     * @bodyParam node string required The node of the user. Example: ROSEDAL
     * @bodyParam main_ip string required The main ip of the user. Example: 1.1.1.1 
     * 
     * @response 200 {"status":"success","message":"Bandwidth updated successfully","detail":{"status":"success","message":"User found","detail":{"id":138463,"name":"Kwargs","username":"1.1.1.1","password":"e086aa13","bandwith_plan":"11440k/11440k","main_ip":"1.1.1.1","node":"ROSEDAL","created_at":"2023-06-08","updated_at":"2023-06-08"}}}
     * @response 400 {"status":"error","message":"Error updating user","detail":{"main_ip":["The main ip must be a valid IP address."]}}
     * @response 500 {"status": "error", "message": "An error occurred while updating the user."}
     * 
    */

    /*
     * Update user params in the radius database
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
     * Delete User
     * 
     * This endpoint allows you to delete a user in the radius database.
     * 
     * @authenticated
     * 
     * @bodyParam username string required The username of the user. Example: 1.1.1.1
     * 
     * @response 200 {"status":"success","message":"User deleted successfully"}
     * @response 400 {"status":"error","message":"Error deleting user","detail":{"username":["The username field is required."]}}
     * @response 404 {"status":"error","message":"User does not exist."}
     * @response 500 {"status": "error", "message": "Error deleting user", "detail": "SQLSTATE[HY000] [2002] No such file or directory"}
     * 
    */

    /*
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

    /**
     * 
     * Find User
     * 
     * This endpoint allows you to find a username in the radius database based on the framed ip address.
     * 
     * @authenticated
     * 
     * @bodyParam framed_ip_address string required The framed ip address of the user. Example:
     * 
     * @response 200 {"status":"success","message":"User found successfully","detail":{"username":"
     * @response 400 {"status":"error","message":"Error finding user","detail":{"framed_ip_address":["The framed ip address field is required."]}}
     * @response 404 {"status":"error","message":"User does not exist."}
     * @response 500 {"status": "error", "message": "Error finding user", "detail": "SQLSTATE[HY000] [2002] No such file or directory"}
     * 
     */

    /*  
     * Find a user in the radius database based on the framed ip address.
     *
     * @param Request $request
     * @param string $framed_ip_address
     * @return Illuminate\Http\Response
     */
        
    public function findUser(Request $request)
    {
        try {
            $validate = $this->validateIP($request);
            
            if ($validate['code'] != 200) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error finding user',
                    'detail' => $validate['detail'],
                ], Response::HTTP_BAD_REQUEST);

            } else {

                $data = $request->input();
                $framed_ip_address = $data['framed_ip'];
                
                // Check if the user exists
                $userExists = DB::connection('radius')
                    ->table('radreply')
                    ->where('value', $framed_ip_address)
                    ->exists();

                if (!$userExists) {

                    return response()->json([
                        'status' => 'error',
                        'message' => 'User does not exist.',
                    ], Response::HTTP_NOT_FOUND);

                } else {
                    // Find user from radreply table
                    $user = DB::connection('radius')
                        ->table('radreply')
                        ->where('value', $framed_ip_address)
                        ->first();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'User found successfully',
                        'detail' => $user,
                    ], Response::HTTP_OK);
                }
            }

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'Error finding user',
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
 
        }


    }

    /**
         * Validate the IP
         * 
         * @param Request $request
         * @return array $response
         */

         private function validateIP (Request $request)
         {
             $data = $request->input();
 
             $validator = Validator::make($request->all(), [
                 'framed_ip' => 'required|string',
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

}
