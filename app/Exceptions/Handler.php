<?php

namespace App\Exceptions;

use App\User;
use App\Backend;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Exceptions\Object\ObjectNotFoundException;
use App\Exceptions\Role\RoleNotFoundException;
use App\Exceptions\Role\RoleGetListException;
use App\Exceptions\Schema\SchemaNotFoundException;
use App\Exceptions\Schema\SchemaListGetException;
use App\Exceptions\User\UserNotFoundException;
use App\Exceptions\Backend\TokenExpiredException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($exception instanceof ObjectNotFoundException) {
            return response()->view('errors.object.notfound');
        }        
        if ($exception instanceof SchemaNotFoundException) {
            return response()->view('errors.schema.notfound');
        }        
        if ($exception instanceof RoleNotFoundException) {
            return response()->view('errors.role.notfound');
        }        
        if ($exception instanceof UserNotFoundException) {
            return response()->view('errors.user.notfound');
        }
        // if ($exception instanceof TokenExpiredException) {
        //     $request->user = isset($request->user) ? $request->user : new User;
        //     $request->user->regenerate(app(Backend::Class));
        //     return redirect($request->path());
        // }

        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login'));
    }
}
