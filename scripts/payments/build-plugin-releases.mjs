import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {createRequire} from 'node:module';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('../../',import.meta.url));
const sourceRoot=process.env.OYONX_PLUGIN_SOURCES||'/Users/apple/Downloads/Figma-Plugins';
const compilerRoot=path.join(sourceRoot,'MindMap Pro/node_modules');
const require=createRequire(import.meta.url);
const ts=require(path.join(compilerRoot,'typescript/lib/typescript.js'));
const esbuild=require(path.join(compilerRoot,'esbuild/lib/main.js'));
const configs=[
 {slug:'fontstyle-pro',directory:'FontStyle Pro',entry:'code.js',functions:['generateGuide'],ui:'ui.html'},
 {slug:'colorstyle-pro',directory:'ColorStylePro',entry:'code.js',functions:['applyPlan'],ui:'ui.html'},
 {slug:'font-replacer-pro',directory:'Font-replacer-Pro',entry:'src/main.ts',method:'apply',ui:'dist/ui.html'},
 {slug:'design-system-creator',source:process.env.OYONX_DESIGN_SYSTEM_SOURCE||'/Users/apple/Downloads/FewClicks/work/repos/design-system-creator',entry:'code.js',functions:['createDesignSystem','applyAdvancedSelection'],ui:'ui.html'},
 {slug:'iconsset-pro',directory:'iconSet Pro',entry:'src/code.ts',messageTypes:['insert','insert-kit','code-card','export'],ui:'dist/ui.html'},
 {slug:'stickynotes-pro',directory:'Sticky Notes Widget Pro',entry:'src/code.tsx',widget:true,messageTypes:['save-text'],ui:'dist/ui.html'},
 {slug:'mindmap-pro',directory:'MindMap Pro',entry:'widget-src/code.tsx',widget:true,ui:null}
];
const output=path.join(root,'.release/paid-plugins');fs.mkdirSync(output,{recursive:true});
const audit=[];
for(const config of configs){
 const source=config.source||path.join(sourceRoot,config.directory),destination=path.join(output,config.slug);
 fs.mkdirSync(destination,{recursive:true});
 const stage=path.join(destination,'source');fs.mkdirSync(stage,{recursive:true});
 for(const dir of ['src','widget-src'])if(fs.existsSync(path.join(source,dir)))fs.cpSync(path.join(source,dir),path.join(stage,dir),{recursive:true});
 if(config.entry==='code.js')fs.copyFileSync(path.join(source,'code.js'),path.join(stage,'code.js'));
 // Keep runtime singleton at project root; all transformed modules import the same instance.
 const runtime=fs.readFileSync(path.join(root,'payments/sdk/figma-main.mjs'),'utf8')+`\nexport const license=createLicense(${JSON.stringify(config.slug)});\n`;
 fs.writeFileSync(path.join(stage,'oyonx-license.mjs'),runtime);
 let transformedCount=0,guards=0;
 function transform(file){
  const text=fs.readFileSync(file,'utf8'),kind=file.endsWith('.tsx')?ts.ScriptKind.TSX:file.endsWith('.ts')?ts.ScriptKind.TS:ts.ScriptKind.JS;
  const parsed=ts.createSourceFile(file,text,ts.ScriptTarget.Latest,true,kind);
  const factory=ts.factory;
  let touched=false;
  const licenseMethod=name=>factory.createPropertyAccessExpression(factory.createIdentifier('OyonxLicense'),name);
  const call=(name,args)=>{touched=true;return factory.createCallExpression(licenseMethod(name),undefined,args);};
  const asyncBlock=body=>factory.createArrowFunction([factory.createModifier(ts.SyntaxKind.AsyncKeyword)],undefined,[],undefined,factory.createToken(ts.SyntaxKind.EqualsGreaterThanToken),body);
  const wrapBody=body=>factory.createBlock([factory.createReturnStatement(call('run',[asyncBlock(body)]))],true);
  const visitor=node=>{
   node=ts.visitEachChild(node,visitor,context);
   if(config.slug==='mindmap-pro' && ts.isExpressionStatement(node) && ts.isCallExpression(node.expression)){
    const expression=node.expression, name=expression.expression.getText(parsed);
    if(name==='figma.widget.waitForTask')return factory.createReturnStatement(expression.arguments[0]);
    if(['handleExport','applyTemplate'].includes(name))return factory.createReturnStatement(expression);
   }
   if(config.slug==='mindmap-pro' && ts.isCallExpression(node) && node.expression.getText(parsed)==='figma.notify' && node.arguments[0] && ts.isStringLiteral(node.arguments[0]) && ['MindMap exported to canvas','Template created as a new map'].includes(node.arguments[0].text))return factory.createCommaListExpression([call('markChanged',[]),node]);
   if(ts.isFunctionDeclaration(node)&&node.name&&config.functions?.includes(node.name.text)&&node.body){guards++;return factory.updateFunctionDeclaration(node,node.modifiers,node.asteriskToken,node.name,node.typeParameters,node.parameters,node.type,wrapBody(node.body));}
   if(ts.isMethodDeclaration(node)&&node.name.getText(parsed)===config.method&&node.body){guards++;return factory.updateMethodDeclaration(node,node.modifiers,node.asteriskToken,node.name,node.questionToken,node.typeParameters,node.parameters,node.type,wrapBody(node.body));}
   if(ts.isCallExpression(node)&&node.expression.getText(parsed)==='figma.ui.postMessage')return call('postMessage',node.arguments);
   if(config.widget&&ts.isCallExpression(node)&&ts.isIdentifier(node.expression)&&['useSyncedState','useSyncedMap'].includes(node.expression.text)){
    if(node.arguments[0]?.getText(parsed)==="'mindmap-interaction-v2'")return node;
    return call(node.expression.text==='useSyncedState'?'trackedState':'trackedMap',[node]);
   }
   if(config.widget&&ts.isCallExpression(node)&&ts.isIdentifier(node.expression)&&node.expression.text==='usePropertyMenu'&&node.arguments.length===2){guards++;return factory.updateCallExpression(node,node.expression,node.typeArguments,[node.arguments[0],call('widgetMenu',[node.arguments[1]])]);}
   if(config.widget&&ts.isJsxAttribute(node)&&['onClick','onTextEditEnd'].includes(node.name.text)&&node.initializer&&ts.isJsxExpression(node.initializer)&&node.initializer.expression){
    const expression=node.initializer.expression;
    if(expression.getText(parsed).includes('openEditor('))return node;
    guards++;return factory.updateJsxAttribute(node,node.name,factory.createJsxExpression(undefined,call('widgetEvent',[expression])));
   }
   if(config.messageTypes&&ts.isBinaryExpression(node)&&node.left.getText(parsed)==='figma.ui.onmessage'&&node.operatorToken.kind===ts.SyntaxKind.EqualsToken){guards++;return factory.updateBinaryExpression(node,node.left,node.operatorToken,call('wrapHandler',[node.right,factory.createArrayLiteralExpression(config.messageTypes.map(x=>factory.createStringLiteral(x)))]));}
   return node;
  };
  let context;const result=ts.transform(parsed,[ctx=>{context=ctx;return node=>ts.visitNode(node,visitor);}]);
  let out=ts.createPrinter().printFile(result.transformed[0]);result.dispose();
  if(touched){const relative='./'+path.relative(path.dirname(file),path.join(stage,'oyonx-license.mjs')).replaceAll(path.sep,'/');out=`import {license as OyonxLicense} from ${JSON.stringify(relative)};\n`+out;transformedCount++;}
  // Account required at startup for normal plugins; widgets preserve document rendering.
  if(file===path.join(stage,config.entry)&&!config.widget){
   if(!touched)throw Error('Entry did not receive licensing import');
   const reparsed=ts.createSourceFile(file,out,ts.ScriptTarget.Latest,true,kind);
   const imports=reparsed.statements.filter(ts.isImportDeclaration).map(n=>n.getFullText(reparsed)).join('\n');
   const body=reparsed.statements.filter(n=>!ts.isImportDeclaration(n)).map(n=>n.getFullText(reparsed)).join('\n');
   out=imports+`\n(async()=>{try{await OyonxLicense.requireAccount();\n${body}\n}catch(error){figma.notify(error.message||String(error),{error:true});}})();\n`;
  }
  fs.writeFileSync(file,out);
 }
 function walk(dir){for(const entry of fs.readdirSync(dir,{withFileTypes:true})){const file=path.join(dir,entry.name);if(entry.isDirectory())walk(file);else if(/\.(?:tsx?|js)$/.test(entry.name))transform(file);}}
 walk(stage);
 if(guards===0)throw Error('No primary action guards added: '+config.slug);
 await esbuild.build({entryPoints:[path.join(stage,config.entry)],bundle:true,outfile:path.join(destination,'code.js'),target:'es2017',format:'iife',jsxFactory:'figma.widget.h',jsxFragment:'figma.widget.Fragment',loader:{'.html':'text'},define:{__APP_VERSION__:'"paid-access-preview"'},logLevel:'silent'});
 const manifest=JSON.parse(fs.readFileSync(path.join(source,'manifest.json'),'utf8'));
 manifest.main='code.js';if(config.ui){fs.copyFileSync(path.join(source,config.ui),path.join(destination,'ui.html'));manifest.ui='ui.html';}else delete manifest.ui;
 const domains=(manifest.networkAccess?.allowedDomains||[]).filter(x=>x!=='none');
 manifest.networkAccess={...manifest.networkAccess,allowedDomains:[...new Set([...domains,'https://oyonx.com'])],reasoning:'Verify Oyonx account, free uses and paid subscription access; document content is not sent.'};
 fs.writeFileSync(path.join(destination,'manifest.json'),JSON.stringify(manifest,null,2)+'\n');
 audit.push({slug:config.slug,source,entry:config.entry,guards,transformedFiles:transformedCount,sourceEntrySha256:createHash('sha256').update(fs.readFileSync(path.join(source,config.entry))).digest('hex'),releaseSha256:createHash('sha256').update(fs.readFileSync(path.join(destination,'code.js'))).digest('hex'),figmaTested:false});
}
fs.writeFileSync(path.join(output,'BUILD-REPORT.json'),JSON.stringify(audit,null,2)+'\n');
console.log(JSON.stringify(audit.map(({slug,guards,transformedFiles})=>({slug,guards,transformedFiles})),null,2));
console.log('Built staged artifacts only. Original sources unchanged. Import each manifest into Figma and verify before publication.');
