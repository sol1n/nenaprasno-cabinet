<?php

namespace App\Traits\Controllers;

use App\Form;
use App\AppObject;
use App\Backend;
use App\Services\ObjectManager;
use App\Services\SchemaManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Exceptions\ActionNotFoundException;

trait FormMethods
{
    /**
     * Decodes raw json from "result" field to form answers array
     * @param  App\Object $response
     * @return array of saved form asnwers
     */
    private function parseAnswers(AppObject $response): array
    {
        if ($response && isset($response->fields['result'])) {
            return json_decode($response->fields['result'], 1);
        } else {
            return [];
        }
    }

    /**
     * Checks if form has quis type
     * @param  Form  $form
     * @return boolean
     */
    private function isQuiz(Form $form): bool
    {
        return mb_strpos($form->title, 'Викторина') !== false ||
            mb_strpos($form->title, 'викторина') !== false ||
            mb_strpos($form->title, 'Quiz') !== false ||
            mb_strpos($form->title, 'quiz') !== false;
    }

    /**
     * Checks if form has speaker questions type
     * @param  Form    $form
     * @return boolean
     */
    private function isSpeakerQuestions(Form $form): bool
    {
        $questions = $form->questions ?? null;
        return $questions && $questions->has('c1') && $questions->count() == 1;
    }

    /**
     * Checks if form has poll type
     * @param  Form    $form
     * @return boolean
     */
    private function isPoll(Form $form): bool
    {
        return !$this->isSpeakerQuestions($form) && !$this->isQuiz($form);
    }

    /**
     * Returns array of form correct answers
     * @param  SchemaManager $schemas
     * @param  ObjectManager $objects
     * @param  int $id form id
     * @return array mapped as ['question key' => right value]
     */
    private function getCorrectAnswers(SchemaManager $schemas, ObjectManager $objects, int $id): array
    {
        $object = $objects->search(
            $schemas->find('CorrectAnswers'),
            ['take' => -1, 'where' => json_encode(['formId' => (string) $id])]
        )->first();
        if ($object && isset($object->fields['answers'])) {
            return json_decode($object->fields['answers'], 1);
        } else {
            return [];
        }
    }

    /**
     * Fills up "correctAnswers" field for response object
     * @param  AppObject $response
     * @param  array  $correctAnswers returned by getCorrectAnswers method
     * @return AppObject with correctAnswers field
     */
    private function checkCorrects(AppObject $response, array $correctAnswers): AppObject
    {
        $corrects = [];
        $response->parsed = $this->parseAnswers($response);
        $response->correctAnswersCount = 0;
        foreach ($response->parsed as $index => $answer) {
            if (isset($correctAnswers[$index]) && $correctAnswers[$index] == $answer) {
                $corrects[$index] = true;
                $response->correctAnswersCount++;
            } else {
                $corrects[$index] = false;
            }
        }

        $response->correctAnswers = $corrects;
        
        return $response;
    }

    /**
     * Returns responses for form
     * @param  ObjectManager $objects
     * @param  SchemaManager $schemas
     * @param  $id form id
     * @return Collection
     */
    private function getResponses(ObjectManager $objects, SchemaManager $schemas, $id): Collection
    {
        $answersSchema = $schemas->find('Answers');
        $query = ['take' => -1, 'search' => ['formId' => (string) $id]];

        return $objects->search($answersSchema, $query);
    }
}
