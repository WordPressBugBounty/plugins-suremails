// Webpack loader for zod 4 sources. zod probes for eval support with
// `new Function("")` and compiles JIT validators via `new Function(...)`.
// Both sit behind try/catch, but the minified bundle still contains a
// `Function(` call that strict CSP (`script-src` without `'unsafe-eval'`)
// reports, and that fails `build:check-csp`. Swapping the constructor for one
// that throws makes zod fall back to its interpreted (jitless) path, which it
// already uses whenever eval is blocked. Wired up in webpack.config.js.
// Ref: https://wordpress.org/support/topic/regression-introduced-in-version-1-9-4/
module.exports = function ( source ) {
	return source.replace(
		/const F = Function;/g,
		"const F = function () { throw new EvalError( 'eval disabled' ); };"
	);
};
