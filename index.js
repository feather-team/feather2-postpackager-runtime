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
	}else{
		//处理下engine.config.php
		var file = ret.src['/conf/engine/online.php'];

		if(file){
			var content = file.getContent();
			var userFile = ret.pkg['/conf/engine/engine.user.php'] = feather.file.wrap(feather.project.getProjectPath() + '/conf/engine/user.php');
			userFile.setContent(content);
			userFile.release = '/view/engine.user.php'

			content = feather.util.read(VENDOR_DIR + '/tpl/engine.config.php', true);
			content = content.replace('#combo#', feather.config.get('combo'));
			content = content.replace('#comboCssOnlySameBase#', feather.config.get('cssA2R'));
			file.setContent(content);
		}else{
			feather.log.error('View engine\'s config file [/conf/engine/online.php] is not exists!');
		}
	}
};