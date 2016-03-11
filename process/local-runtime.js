/*
DEV环境 FEATHER 结合模版引擎 进行本地调试所需要的资源生成
*/

'use strict';

module.exports = function(ret, conf, setting, opt){
    var modulename = feather.config.get('project.modulename'), ns = feather.config.get('project.name');
    var www = feather.project.getTempPath('www'), vendor = __dirname + '/../vendor/local-runtime';
    var proj = www + '/proj/' + ns;


    if(feather.config.get('project.mode') == 'php'){
        var root = feather.project.getProjectPath();

        if(modulename){
            if(modulename == 'common'){
                feather.util.del(proj + '/view/map/map.php');
            }
        }else{
            feather.util.del(proj + '/view/map');
        }
    }

    //生成conf
    var hash = {
        ns: ns,
        mode: feather.config.get('project.mode'),
        template: {
            suffix: feather.config.get('template.suffix')
        },
        comboDebug: feather.config.get('comboDebug'),
        media: feather.project.currentMedia()
    };

    feather.util.write(proj + '/conf.php', feather.util.json(hash));
    feather.util.mkdir(proj + '/cache');
    feather.util.write(www + '/index.php', feather.file.wrap(vendor + '/index.php').getContent());
    feather.util.write(www + '/c_proj', ns);

    var rootLen = feather.util.realpath(vendor).length;

    feather.util.find(vendor + '/lib').forEach(function(item){
        feather.util.write(www + item.substring(rootLen), feather.util.read(item));
    });

    feather.util.find(vendor + '/plugins').forEach(function(item){
        var name = item.substring(rootLen);
        var file = feather.file.wrap(feather.project.getProjectPath() + name);
        file.setContent(feather.util.read(item));
        ret.pkg[file.subpath] = file;
    });
};
