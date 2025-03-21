const modules = import.meta.glob(["../../templates/**/*.js", "./custom_elements/*.js"], { eager: true });

export default function defineCustomElements() {

    for (const fileName in modules) {

        const moduleConfig = modules[fileName]?.default;
        const moduleName = fileName.split('/').pop().replace(/\.\w+$/, '');

        if (typeof moduleConfig !== 'function') {

            console.warn(`Module ${fileName} is not a valide Classname`);
            continue;
        }

        customElements.define('x-'+moduleName, moduleConfig);
    }
}