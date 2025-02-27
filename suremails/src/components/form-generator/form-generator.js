import { Input, Select, Label, Checkbox } from '@bsf/force-ui';
import TruncateText from '@components/truncate-text';
import parse from 'html-react-parser';
import DOMPurify from 'dompurify';

const SHOW_MORE_CHARACTER_LIMIT = 50;

const HelpText = ( { text, showMore = false } ) => {
	// For security, override target and rel attributes to links
	DOMPurify.addHook( 'afterSanitizeAttributes', function ( node ) {
		// set all elements owning target to target=_blank and rel=noopener noreferrer
		if ( 'target' in node ) {
			node.setAttribute( 'target', '_blank' );
			node.setAttribute( 'rel', 'noopener noreferrer' );
		}
	} );
	return (
		<Label tag="span" size="sm" variant="help">
			{ !! showMore ? (
				<TruncateText
					html={ text }
					characterLimit={ SHOW_MORE_CHARACTER_LIMIT }
				/>
			) : (
				<span>{ parse( DOMPurify.sanitize( text ) ) }</span>
			) }
		</Label>
	);
};

const FormField = ( {
	field,
	value,
	onChange,
	errors,
	inlineValidator,
	formStateValues,
} ) => {
	const handleChange = ( newValue ) => {
		let convertedValue = newValue;
		try {
			// If field type is number, convert to number
			convertedValue =
				field.type === 'number' ? Number( newValue ) : newValue;
		} catch ( error ) {
			// Do nothing
		}
		// If field has a transform function, apply it
		const transformedValue = field.transform
			? field.transform( convertedValue )
			: convertedValue;
		onChange?.( field.name, transformedValue );
	};

	switch ( field.input_type ) {
		case 'text':
		case 'password':
		case 'email':
		case 'number':
			return (
				<div className="flex flex-col gap-1.5 py-2 pl-2">
					<Input
						name={ field.name }
						type={ field.input_type }
						size="md"
						value={ value || '' }
						onChange={ handleChange }
						onBlur={ inlineValidator }
						error={ errors?.[ field.name ] }
						placeholder={ field.placeholder }
						label={ field.label }
						required={ field.required }
						className="w-full"
						min={ field.min }
						autoComplete="off"
					/>
					{ !! errors?.[ field.name ] && (
						<p className="text-text-error text-sm">
							{ errors?.[ field.name ] }
						</p>
					) }
					{ field.help_text && (
						<HelpText
							text={ field.help_text }
							showMore={ field?.helpShowMore }
						/>
					) }
				</div>
			);

		case 'select':
			return (
				<div className="flex flex-col gap-1.5 py-2 pl-2">
					<Label
						size="sm"
						className="w-full"
						required={ field.required }
					>
						{ field.label }
					</Label>
					<Select
						value={ value ?? field.default }
						onChange={ handleChange }
						className="w-full h-10"
						combobox={ field.combobox }
					>
						<Select.Button
							type="button"
							render={ ( selectedValue ) =>
								field.getOptionLabel?.( selectedValue ) ??
								selectedValue
							}
						/>
						<Select.Options className="z-999999">
							{ field.options.map( ( option ) => (
								<Select.Option
									key={ option.label }
									value={ option.value }
								>
									{ option.label }
								</Select.Option>
							) ) }
						</Select.Options>
					</Select>
					{ field.help_text && (
						<HelpText
							text={ field.help_text }
							showMore={ field?.helpShowMore ?? false }
						/>
					) }
				</div>
			);

		case 'checkbox':
			return (
				<div className="p-2">
					<Checkbox
						name={ field.name }
						checked={ value }
						size="sm"
						onChange={ handleChange }
						label={ {
							heading: field.label,
							description: field.help_text && (
								<HelpText
									text={ field.help_text }
									showMore={ field?.helpShowMore ?? false }
								/>
							),
						} }
						disabled={ field.disabled?.( value, formStateValues ) }
					/>
				</div>
			);

		default:
			return null;
	}
};

const FormGenerator = ( {
	fields,
	values,
	onChange,
	errors,
	inlineValidator,
} ) => {
	const handleFieldChange = ( field, value ) => {
		onChange?.( { [ field ]: value } );
	};

	return (
		<div className="flex flex-col gap-4">
			{ fields.map( ( field ) => (
				<FormField
					key={ field.name }
					field={ field }
					value={ values?.[ field.name ] }
					formStateValues={ values }
					onChange={ handleFieldChange }
					errors={ errors }
					inlineValidator={ inlineValidator }
				/>
			) ) }
		</div>
	);
};

export default FormGenerator;
