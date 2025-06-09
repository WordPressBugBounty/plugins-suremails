import FormGenerator from '@components/form-generator';

const ExtendedDynamicForm = ( {
	fields,
	connectionData,
	onChange,
	errors,
	inlineValidator,
	onClickAuthenticate,
} ) => {
	const handleFieldChange = ( updates ) => {
		if ( typeof onChange !== 'function' ) {
			return;
		}

		onChange?.( updates );
	};

	return (
		<FormGenerator
			fields={ fields }
			values={ connectionData }
			onChange={ handleFieldChange }
			errors={ errors }
			inlineValidator={ inlineValidator }
			onClickAuthenticate={ onClickAuthenticate }
		/>
	);
};

export default ExtendedDynamicForm;
