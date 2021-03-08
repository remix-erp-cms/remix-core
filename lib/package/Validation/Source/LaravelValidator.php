<?php namespace Package\Validation\Source;

use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Factory;
use Package\Validation\ValidableInterface;
use Package\Validation\AbstractValidator;

abstract class LaravelValidator extends AbstractValidator {

    /**
     * @var Factory
     */
    protected $validator;

    /**
     * LaravelValidator constructor.
     * @param Factory $validator
     */
    public function __construct(Factory $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Pass the data and the rules to the validator
     *
     * @return boolean
     */
    public function passes()
    {
        $validator = $this->validator->make($this->data, $this->rules);
        if(isset($this->message))
        {
//            $local = Lang::locale();
//            $res_lang = $this->readFileLang($local);
//            if ($local == 'en') {
//                foreach ($this->message as $key=>$value)
//                {
//                    $this->message[$key] = $res_lang[$value];
//                }
//            }
//            if ($local == 'ja') {
//                foreach ($this->message as $key=>$value)
//                {
//                    $this->message[$key] = $res_lang[$value];
//                }
//            }
            $message = $this->message;
            $validator = $this->validator->make($this->data, $this->rules,$message);
        }

        if( $validator->fails() )
        {
            $this->errors = $validator->messages()->messages();
            return false;
        }

        return true;
    }

    private function readFileLang($local)
    {
        $path = resource_path('lang/' . $local . '/validation.php' );
        return require ($path);
    }
}
