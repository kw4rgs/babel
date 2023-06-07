<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Validator;

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
     * Get a user from the radius database based on the username in the request
     *
     * @param Request $request 
     * @return Illuminate\Http\Response
     */

    public function getUser (Request $request)
    {
        try {
            $username = $request->input('username');
    
            if (empty($username)) {
                throw new ValidationException('Username is required.');
            }
    
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
    
            $response = [
                'status' => 'success',
                'code' => 200,
                'data' => $data_radius,
            ];
    
            return response()->json($response, $response['code']);
    
        } catch (ValidationException $e) {
            $response = [
                'status' => 'error',
                'code' => 400,
                'message' => $e->getMessage(),
            ];
    
        } catch (NotFoundHttpException $e) {
            $response = [
                'status' => 'error',
                'code' => 404,
                'message' => 'User does not exist.',
            ];
    
        } catch (\Exception $e) {
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'An error occurred while retrieving user data.',
            ];
        }
    
        return response()->json($response, $response['code']);
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

            $validate = $this->validateInput($request);
            
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

        private function validateInput (Request $request)
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
            $data = $request->input();
    
            $name = ucwords(strtolower($data['name']));
            $username = strtolower($data['username']);
            $bandwidth = $data['bandwidth_plan'];
            $node = strtoupper($data['node']);
            $ip = $data['main_ip'];
    
            if (empty($username) || empty($name) || empty($node) || empty($bandwidth) || empty($ip)) {
                $response = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Missing parameters',
                ];
    
                return response()->json($response, $response['code']);
            }
    
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
    
        } catch (ValidationException $e) {
            $response = [
                'status' => 'error',
                'code' => 400,
                'message' => $e->getMessage(),
            ];
    
        } catch (NotFoundHttpException $e) {
            $response = [
                'status' => 'error',
                'code' => 404,
                'message' => 'User does not exist.',
            ];
    
        } catch (\Exception $e) {
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'An error occurred while updating the username.',
                'error' => $e->getMessage(),
            ];
        }
    
        return response()->json($response, $response['code']);
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
