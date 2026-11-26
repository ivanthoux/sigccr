import matplotlib; matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch
plt.rcParams['font.family']='Liberation Sans'
fig,ax=plt.subplots(figsize=(12,8.6)); ax.set_xlim(0,120); ax.set_ylim(0,86); ax.axis('off')
B=dict(fc='#DAE8FC',ec='#6C8EBF'); Y=dict(fc='#FFF2CC',ec='#D6B656'); G=dict(fc='#E6E6E6',ec='#808080')
def band(y,h,title,x=1,w=96):
    ax.add_patch(FancyBboxPatch((x,y),w,h,boxstyle='round,pad=0,rounding_size=1.2',fc='#F5F5F5',ec='#999999',lw=1))
    ax.text(x+1.2,y+h-1.6,title,fontsize=10,fontweight='bold',va='top')
def box(x,y,w,h,t,st=B,fs=8.6):
    fs=fs*0.86
    ax.add_patch(FancyBboxPatch((x,y),w,h,boxstyle='round,pad=0,rounding_size=0.8',lw=1.1,**st))
    ax.text(x+w/2,y+h/2,t,ha='center',va='center',fontsize=fs,linespacing=1.3)
def arr(x1,y1,x2,y2,**k):
    ax.annotate('',xy=(x2,y2),xytext=(x1,y1),arrowprops=dict(arrowstyle='-|>',color='#333333',lw=1,**k))
band(73,12,'Presentación  ·  app/Views, public/assets')
box(4,74.5,44,6.5,'Panel administrativo\nBootstrap 5 · DataTables con procesamiento en el servidor (RNF-07)')
box(52,74.5,42,6.5,'Portal de autogestión del cliente\nBootstrap 5 · diseño adaptable desde 360 px (RNF-02)')
band(50,21,'Control  ·  app/Filters, app/Controllers, app/Commands')
box(4,61.5,90,6,'Filtros de CodeIgniter: forcehttps · secureheaders (CSP, HSTS) · csrf · auth (sesión y version_seguridad) ·\npermiso:<código> por ruta · pertenencia del cliente · throttle (RN-62) · mantenimiento del portal (RNF-13)',fs=8.2)
for i,(t) in enumerate(['Controllers\\Admin\nun controlador por recurso','Controllers\\Portal\nsolo datos del cliente autenticado','Controllers\\Api\\Webhooks\nregistra y responde el acuse','Commands (spark)\nprocesos programados']):
    box(4+i*23,51.5,21,7,t,fs=8)
band(25,23,'Servicios de negocio  ·  app/Services/<Subsistema>  (reglas de negocio, transacciones)')
subs=['Acceso\nSS-01','Clientes\nSS-02','Líneas\nSS-03','Solicitudes\nSS-04','Caja y fondos\nSS-05','Cartera\nSS-06','Portal\nSS-07','Campañas\nSS-08','Reportes\nSS-09','Mantenim.\nSS-11']
for i,t in enumerate(subs): box(3.5+i*9.15,37,8.4,6.5,t,fs=7.8)
box(4,27,52,8,'Motor financiero  ·  app/Libraries/Finanzas  (sin dependencias del framework)\nDecimal (BCMath, redondeo propio) · AmortizacionFrancesa · Punitorios ·\nCapacidadPago · Cftea · Refinanciacion · CalendarioHabil  (RNF-15)',Y,fs=8)
box(59,27,35,8,'Contratos de integración  ·  app/Adapters\n«interface» InformacionCrediticia · Padron ·\nPasarela · Comunicaciones\ncon implementación real y simulador (RNF-05)',fs=8)
band(9,14,'Persistencia  ·  app/Models, app/Entities')
box(4,10.5,52,8,'Modelos CodeIgniter (uno por tabla)\nQuery Builder con consultas parametrizadas (RNF-12) · sin reglas de negocio\nbloqueos UPDLOCK y control de version_fila para concurrencia (RNF-06)',fs=8)
box(59,10.5,35,8,'Clientes HTTP de los adaptadores\nCURLRequest · TLS 1.2+ · espera máxima ·\nreintentos · registro en intercambios_externos',fs=8)
box(14,0.5,32,6,'SQL Server 2022\n86 tablas (4.3)',G)
box(62,0.5,29,6,'Servicios externos\nBCRA · Pasarela · Correo',G)
ax.add_patch(FancyBboxPatch((99.5,9),19.5,62,boxstyle='round,pad=0,rounding_size=1.2',fc='#F5F5F5',ec='#999999'))
ax.text(109.25,69,'Servicios comunes\nSS-10 (transversal)',ha='center',va='top',fontsize=10,fontweight='bold')
for i,t in enumerate(['AuditoriaService\n(RN-45)','AlertaService\n(CU-83)','ComunicacionService\ncola y reintentos (RNF-09)','ParametroService\nversiones (RN-15)','Registro técnico\nlogs sin datos\nsensibles (RNF-16)','EjecucionProceso\nbloqueo sp_getapplock\n(RNF-10)']):
    box(101,55-i*8.6,16.5,7.4,t,fs=7.6)
arr(26,74.5,26,71); arr(73,74.5,73,71)
for i in range(3): arr(14.5+i*23,61.5,14.5+i*23,58.5)
for i in range(4): arr(14.5+i*23,51.5,14.5+i*23,48)
arr(30,37,30,35.1); arr(76,37,76,35.1)
arr(30,27,30,23); arr(76,27,76,23)
arr(30,10.5,30,6.6); arr(76,10.5,76,6.6)
arr(97,40,101,40)
ax.text(1,-1.5,'Las flechas indican dependencia: cada capa solo utiliza la inmediata inferior; los servicios comunes son utilizados por todas.',fontsize=7.5,style='italic')
for ext in ('png', 'svg'): plt.savefig(f'logica.{ext}', dpi=200, bbox_inches='tight', facecolor='white')
