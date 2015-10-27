# symfony2-validator
Validate form data before processing

# Example use library:

```php
// Include library or call from service
use CommonBundle\Component\Validator;

// Init validator
$redirectRoute = 'product';
$requestParams = array(
	'name' => 'test name'
);
$validateRules = array(
	'name' => array(
		'rules' => 'required|max_length=20',
		'message' => array(
			'Name is required',
			'Name max length 20 letters'
		)
	)
);

$validator = $this->get('common.validator');
$validator->setParams($requestParams);
$validator->setRules($validateRules);
$validateResult = $validator->run();
if ($validateResult['errors']) {
	return $this->redirectToRoute($redirectRoute);
}

# Example call flash session form data:

$validator = $this->get('common.validator');
$formData = $validator->getFlashData(Validator::FORM_DATA);
```

See more pattern in $ruleConst variable
