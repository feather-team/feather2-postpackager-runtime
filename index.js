'use strict';

module.exports = function(ret, conf, setting, opt){
	var modulename = feather.config.get('modulename'), ROOT = feather.project.getProjectPath(), VENDOR_DIR = __dirname + '/vendor';

	if(modulename == 'common' || !modulename){
		var staticPath = ROOT + '/_static_.' + feather.config.get('template.suffix');
		var staticFile = new feather.file(staticPath);
		staticFile.setContent(feather.file.wrap(VENDOR_DIR + '/tpl/static.php').getContent());
        ret.pkg[staticPath] = staticFile;
    }

	['autoload_static', 'script_collection'].forEach(function(name){
		var path = '/plugins/' + name + '.php';
		var file = feather.file.wrap(ROOT + path);
	    file.setContent(feather.file.wrap(VENDOR_DIR + path).getContent());
	    ret.pkg[file.subpath] = file;
	});

	if(opt.dest == 'preview'){
		require('./process/local-runtime.js')(ret, conf, setting, opt);
	}
};