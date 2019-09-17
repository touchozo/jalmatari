<?php

namespace Jalmatari;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Jalmatari\Funs\Funs;

class JalmatariHandler extends ExceptionHandler
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
     * @param  \Exception $exception
     * @return void
     */
    public function report(Exception $exception)
    {

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {


        $e_name = explode('\\', get_class($exception));
        $e_name = $e_name[ count($e_name) - 1 ];
        
        $this->isJalmatariTablesMissing($exception->getMessage());
        $showErrors = \Schema::hasTable('settings') && !Funs::BoolSetting('show_errors');
        if (!in_array($request->path(), [ 'register' ]) && $showErrors) {

            $e_name = explode('\\', get_class($exception));
            $e_name = $e_name[ count($e_name) - 1 ];
            $rendered_page = parent::render($request, $exception);
            errors::insert(
                [
                    'user_id'        => auth()->check() ? auth()->user()->id : 0,
                    'request'        => $request,
                    'rendered_page'  => $rendered_page->original,
                    'exception'      => $exception,
                    'exception_name' => $e_name,
                    'url'            => urldecode($request->fullUrl()),
                    'exception_msg'  => $exception->getMessage()
                ]
            );
            $error = errors::all()->last();
            $data = [
                'error'      => $error,
                'error_name' => $e_name,
            ];
            $view = view('error', $data)->render();
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
            die($view);
        }

        return parent::render($request, $exception);
    }

    public function isJalmatariTablesMissing($msg)
    {
        $isMissing = false;
        $dbPrefx = Funs::DB_Prefix();
        $dbName = Funs::DB_Name();

        $tables = [
            "errors",
            "groups",
            "menu",
            "permissions",
            "settings",
            "sync",
            "tables",
            "tables_cols",
            "routes",
            "controllers",
            "users",
            "users_settings",
        ];

        if (strpos($msg, 'table or view not found') >= 1)
            foreach ($tables as $table)
                if (strpos($msg, "Table '$dbName.{$dbPrefx}{$table}' doesn't exist") >= 1) {
                    $tablesNotExists[] = $table;
                    $isMissing = true;
                    break;
                }

        if ($isMissing)
            $this->showTableError($tables);




        return $isMissing;
    }

    public function showTableError($tables)
    {
        $data = [
            'title'   => 'Error - Tables not Exists',
            'tables' => $tables
        ];
        http_response_code(501);

        die(view('errors.tables', $data)->render());
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Auth\AuthenticationException $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([ 'error' => 'Unauthenticated.' ], 401);
        }

        return redirect()->guest('login');
    }
}
