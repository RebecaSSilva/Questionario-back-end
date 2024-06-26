<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Form;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class FormController extends Controller
{
    // Endpoint para criar um novo formulário com questões
    public function create(Request $request)
    {
        // Verifica se há um usuário autenticado
        if (auth()->check()) {
            $user_id = auth()->user()->id;
    
            try {
                // Validar os dados do formulário e das questões
                $data = $request->validate([
                    'title' => 'required|string',
                    'url' => 'nullable|string',
                    'button_color' => 'nullable|string',
                    'question_color' => 'nullable|string',
                    'answer_color' => 'nullable|string',
                    'background_color' => 'nullable|string',
                    'background_image' => 'nullable|string',
                    'logo' => 'nullable|string',
                    'font' => 'nullable|string',
                    'questions' => 'nullable|array',
                    'questions.*.field_title' => 'required|string',
                    'questions.*.field_description' => 'nullable|string',
                    'questions.*.field_type' => 'required|string',
                    'questions.*.is_last' => 'nullable|boolean',
                    'questions.*.mandatory' => 'nullable|boolean',
                    'questions.*.value_key' => [
                        'nullable',
                        'string'
                    ],
                ]);
            } catch (ValidationException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
    
            // Adicionar o ID do usuário aos dados do formulário
            $data['user_id'] = $user_id;
    
            try {
                // Cria o formulário com os dados fornecidos
                $form = Form::create($data);
    
                $form->url = url('http://127.0.0.1:8000/forms/' . $form->id);
                $form->save();
    
                // Armazena as questões associadas ao formulário
                $createdQuestions = [];
                if (isset($data['questions'])) {
                    foreach ($data['questions'] as $questionData) {
                        // Gerar o field_slug com base em um identificador único
                        $questionData['field_slug'] = uniqid();
                        $questionData['form_id'] = $form->id;
                        $createdQuestions[] = Question::create($questionData);
                    }
                }
    
                $form->load('questions');
    
                return response()->json(['form' => $form, 'questoes' => $createdQuestions], 201, [], JSON_UNESCAPED_UNICODE);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erro ao criar o formulário: ' . $e->getMessage()], 500);
            }
        } else {
            // Se o usuário não estiver autenticado, retorne uma resposta de erro
            return response()->json(['error' => 'Usuário não autenticado.'], 401);
        }
    }

    // Endpoint para mostrar os detalhes de um formulário específico
    public function show($id)
    {
        // Encontrar e retornar o formulário com o ID fornecido
        $form = Form::findOrFail($id);
        
        // Carregar as perguntas associadas ao formulário
        $user = Auth::user();
        if (!$user || $form->user_id !== $user->id) {
            return response()->json(['error' => 'Você não tem permissão para visualizar este formulário.'], 403);
        }

        $form->load('questions');

        return response()->json($form, 200, [], JSON_UNESCAPED_UNICODE);
    }

   // Endpoint para listar todos os formulários do usuário com o total de pessoas que responderam ao menos uma pergunta
    public function list(Request $request)
    {

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Usuário não autenticado.'], 401);
        }
        // Se o usuário estiver autenticado, retorne seus formulários com suas questões
        $forms = $request->user()->forms()->with('questions')->get();

        // Transforma a coleção de formulários para adicionar o total de pessoas que responderam ao menos uma pergunta
        $formsWithResponseCount = $forms->map(function ($form) {
            // Obter o número total de pessoas que responderam ao menos uma pergunta neste formulário
            $totalPeople = Answer::where('form_id', $form->id)->distinct('public_user_id')->count();

            return [
                'form' => $form,    
                'total_pessoas' => $totalPeople 
            ];
        });

        return response()->json($formsWithResponseCount, 200, [], JSON_UNESCAPED_UNICODE);
    }

}
