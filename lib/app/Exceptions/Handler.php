<?php

namespace App\Exceptions;

use App\Mail\ExceptionOccured;
use Throwable;
use Illuminate\Auth\AuthenticationException;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Mail;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;

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
     * @param  \Throwable   $exception
     * @return void
     */
    public function report(Throwable  $exception)
    {
        if ($this->shouldReport($exception)) {
            if (config('app.env') == 'demo') {
                $this->sendEmail($exception); // sends an email in demo server
            }
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable   $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable  $exception)
    {
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
            $data = [
                'success' => true,
                'msg' => "Chưa đăng nhập",
                'data' => null,
                'code' => 401,
                'errors' => null
            ];

            return response()->json($data, 200);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Sends the exception email in demo server
     *
     * @param $exception
     */
    public function sendEmail(Exception $exception)
    {
        try {
            $e = FlattenException::create($exception);

            $handler = new SymfonyExceptionHandler();

            $html = $handler->getHtml($e);
            $email = config('mail.username');
            
            if (!empty($email)) {
                Mail::to($email)->send(new ExceptionOccured($html));
            }
        } catch (Exception $ex) {
            dd($ex);
        }
    }
}
