<?php

namespace App\Http\Controllers;

use Error;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    
    public function index(Request $request)
    {
        //
        // $users = User::all();

        $query = User::query();

        if($request->has('role')){
            $query->where('role',$request->role);
        }

        $users = $query->get();

        if($users->isEmpty()){
            return response()->json(['Message' => 'No hay usuarios registrados'],404);
        }
        return response()->json($users,200);
    }

    //Controlador de registro:
    public function store(Request $request)
    {
        try{
            //Bail para que se detenga la validación una vez se detecte el error en ese campo
            $validatedData = Validator::make($request->all(),[
            'name' => 'bail|required|string|min:3',
            'email' => 'bail|required|string|email|unique:users',
            'role' => 'nullable|string|in:admin,cliente',
            'password' => 'required|string|min:8'
            ], 
        ['email.unique' => 'El email ya existe']);

            if($validatedData->fails()){

                return response()->json([
                    'status' => "error",
                    'mensaje' => "Credenciales de usuario invalidas",
                    'errores' => $validatedData->errors()
                ],401);
            }
            $newUser =  $validatedData->getData();
            $newUser['role'] = $newUser['role'] ?? 'empleado';
            
            $newUser['password'] = Hash::make($newUser['password']);

            $user = User::create($newUser);

            return response()->json([
                'status' => "exito",
                'mensaje' => 'Usuario creado existosamente',
                'user' => $user
            ],201);
        } catch (Exception $e) {
            return response()->json([
                'status' => "error",
                'mensaje' => 'Ha habido un error en el servidor',
                'error' => $e->getMessage()
            ],500);
        }
    }

    public function show(string $id)
    {

        try {
        $user = User::findOrFail($id);
        return response()->json($user, 200);
        } 
        catch (ModelNotFoundException $e) { 
            return response()->json([
                'status' => 'error',
                'message' => 'No existe un usuario con el ID: ' . $id
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error en el servidor'
            ], 500);
        }
    }


    public function update(Request $request, string $id)
    {
        //
        $user = User::find($id);

        if(!$user){
            return response()->json(['message'=>'El usuario no existe'],404);
        }
        //Investigar por que hace esto el $user -> id
        $validatedData = Validator::make($request->all(),[
            'name' => 'sometimes|string|min:3',
            'email' => 'sometimes|string|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|string|in:admin,empleado',
            'password' => 'sometimes|string|min:8'
        ],
        ['email.unique' => 'El email ya existe']
        );

        if($validatedData->fails()){
            return response()->json([
                'status' => "error",
                'mensaje' => "Credenciales invalidas",
                'errores' => $validatedData->errors()
            ],401);
        }
        $newUser = $validatedData ->validated();

        if(isset($newUser['password'])){
           $newUser['password'] = Hash::make($newUser['password']);
        }

        $user->update($newUser);

        return response()->json([
        'status' => 'exito',    
        'message' => 'El usuario ha sido actualizado', 
        'usuario' => $user],200
        );

    }


    public function destroy(string $id)
    {
        //
        $user = User::find($id);
        if(!$user){
            return response()->json(['Message'=>'No existe este usuario'],404);
        }
        $user->delete();
        return response()->json(['Message' => 'El usuario ha sido eliminado correctamente'],200);
    }

    public function login(Request $request) {
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string|min:8'
    ]);

    if ($validator->fails()) {
        return response()->json(['status' => 'error', 'errores' => $validator->errors()], 422);
    }

    $credentials = $request->only('email', 'password');

    try {
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['status' => 'error', 'mensaje' => 'Credenciales inválidas'], 401);
        }
    } catch (JWTException $e) {
        return response()->json(['status' => 'error', 'mensaje' => 'No se pudo crear el token'], 500);
    }

    return response()->json([
        'status' => 'exito',
        'mensaje' => 'Usuario logueado.',
        'token' => $token
    ]);
}

    public function logout(){
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['status'=> 'exito',
        'mensaje' => 'Usuario deslogeado. Eliminado token...',
        ],200);
    }
}