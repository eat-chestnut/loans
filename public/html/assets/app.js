/* ===================== 数据模型 ===================== */
const KEY='loan_admin_demo_v3';
const PAGE_SIZE = 20;
const DAY_MS = 24*60*60*1000;
const UI_STATE_KEY='loan_admin_ui_state';
const loanStatuses = ['all','放款','完结','逾期'];
const uiState = {
  employees:1,
  customers:1,
  customerRisk:'all',
  loans:1,
  loanMin:'',
  loanMax:'',
  repayments:1,
  repaymentFilter:'all',
  overdue:1,
  wecom:1,
  wecomDept:'all',
  smsLogs:1,
  smsQuery:'',
  autopayLogs:1,
  autopayQuery:'',
  autopayStatus:'all',
  wecomLogs:1,
  wecomLogsQuery:'',
  loanStatus:'all',
  notifications:1,
  notificationChannel:'all',
  notificationStatus:'all',
  notificationSearch:'',
  templateSearch:'',
  templates:1
};
loadUIState();
let DB = loadDB();

function uid(){return Math.random().toString(36).slice(2)+Date.now().toString(36).slice(-4)}
function saveDB(){localStorage.setItem(KEY,JSON.stringify(DB));refreshAll()}
function loadDB(){
  const raw=localStorage.getItem(KEY);
  if(raw){
    try{
      const data=JSON.parse(raw);
      if(!data.wecomContacts) data.wecomContacts = defaultWeComContacts();
      if(!Array.isArray(data.smsLogs)) data.smsLogs=[];
      if(!Array.isArray(data.wecomLogs)) data.wecomLogs=[];
      if(!Array.isArray(data.autopayLogs) || !data.autopayLogs.length) data.autopayLogs=generateAutopayLogs(data.customers||[],data.loans||[],data.repayments||[],data.configs||{});
      if(!Array.isArray(data.messageTemplates) || !data.messageTemplates.length) data.messageTemplates=defaultMessageTemplates();
      if(!Array.isArray(data.notificationTasks) || !data.notificationTasks.length) data.notificationTasks=generateNotificationTasks(data.customers||[],data.messageTemplates);
      if(data.configs){
        if(!data.configs.reminder){
          data.configs.reminder={days:5,frequency:3};
        }else if(data.configs.reminder.frequency==null && data.configs.reminder.multiplier!=null){
          const freq=Math.max(1, Math.round(data.configs.reminder.multiplier||1));
          data.configs.reminder={days:data.configs.reminder.days||5,frequency:freq};
        }else{
          data.configs.reminder.frequency = data.configs.reminder.frequency ?? 3;
          data.configs.reminder.days = data.configs.reminder.days ?? 5;
        }
      }
      return data;
    }catch{}
  }
  return seed();
}

function defaultWeComContacts(){
  return [
    {id:'wc-001',name:'王五·企微',dept:'福州一区',wechat:'wx_wangwu',mobile:'13800001234'},
    {id:'wc-002',name:'李四·企微',dept:'福州二区',wechat:'wx_lisi',mobile:'13988886666'},
    {id:'wc-003',name:'小美·企微',dept:'福州三区',wechat:'wx_xiaomei',mobile:'13677773333'},
    {id:'wc-004',name:'赵六·企微',dept:'闽侯支行',wechat:'wx_zhaoliu',mobile:'13766665555'},
    {id:'wc-005',name:'大聪明·企微',dept:'省级大客部',wechat:'wx_dacongming',mobile:'13555559999'}
  ];
}

function defaultMessageTemplates(){
  const now=new Date();
  const base=[
    {id:'tpl-sms-due',channel:'短信',name:'临期还款提醒',category:'催收',variables:['客户姓名','到期日','金额'],content:'尊敬的{{客户姓名}}，您第{{期数}}期应在{{到期日}}前还款{{金额}}元，请及时处理。',lastUsed:addDays(now,-1).toISOString(),owner:'风控运营',retry:{max:3,gap:'30分钟'}},
    {id:'tpl-sms-renew',channel:'短信',name:'复贷邀约',category:'营销',variables:['客户姓名','额度'],content:'{{客户姓名}}，您的专属额度{{额度}}元已就绪，回复1预约复贷。',lastUsed:addDays(now,-5).toISOString(),owner:'业务运营',retry:{max:1,gap:'--'}},
    {id:'tpl-wecom-overdue',channel:'企微',name:'企微逾期关怀',category:'催收',variables:['客户姓名','逾期天数'],content:'{{客户姓名}}，您已逾期{{逾期天数}}天，请联系客户经理协商还款方案。',lastUsed:addDays(now,-2).toISOString(),owner:'企微客服',retry:{max:2,gap:'1小时'}},
    {id:'tpl-wecom-survey',channel:'企微',name:'满意度回访',category:'回访',variables:['客户姓名'],content:'{{客户姓名}}，感谢使用本司贷款，点击链接完成满意度调查。',lastUsed:addDays(now,-12).toISOString(),owner:'运营支持',retry:{max:1,gap:'--'}},
    {id:'tpl-call-collection',channel:'电话',name:'电话催收脚本',category:'催收',variables:['客户姓名','金额'],content:'您好{{客户姓名}}，这里是小贷客服，提醒您当前待还金额{{金额}}元…',lastUsed:addDays(now,-1).toISOString(),owner:'催收团队',retry:{max:5,gap:'15分钟'}},
    {id:'tpl-push-reminder',channel:'APP Push',name:'App Push 临期提醒',category:'催收',variables:['期数','金额'],content:'【小贷】第{{期数}}期{{金额}}元即将到期，点击查看详情。',lastUsed:addDays(now,-3).toISOString(),owner:'移动运营',retry:{max:2,gap:'2小时'}},
    {id:'tpl-push-campaign',channel:'APP Push',name:'权益活动推送',category:'营销',variables:['客户姓名','优惠'],content:'{{客户姓名}}，本周专属优惠{{优惠}}正在进行，点击立即领取。',lastUsed:addDays(now,-15).toISOString(),owner:'市场',retry:{max:1,gap:'--'}}
  ];
  return base;
}

function generateAutopayLogs(customers=[],loans=[],repayments=[],configs={}){
  const autopayChannels=['MockPay','UnionPay','QuickPay','CityPay'];
  const result=[];
  if(!loans.length) return result;
  const total=Math.max(220,loans.length*4);
  for(let i=0;i<total;i++){
    const loan=loans[i%loans.length];
    if(!loan) continue;
    const borrower=customers.find(c=>c.id===loan.customerId) || customers[i%customers.length];
    const plans=repayments.filter(r=>r.loanId===loan.id);
    if(!plans.length) continue;
    const rep=plans[Math.floor(Math.random()*plans.length)]||plans[0];
    const daysAgo=Math.floor(Math.random()*90);
    const hourOffset=Math.floor(Math.random()*24);
    const time=new Date(Date.now()-daysAgo*86400000-hourOffset*3600000).toISOString();
    const statusRoll=Math.random();
    let status='成功'; let message='代扣成功，资金已入账';
    if(statusRoll>0.7 && statusRoll<=0.9){status='重试'; message='通道响应重试，等待下一次代扣';}
    else if(statusRoll>0.9){status='失败'; message='代扣失败，请人工干预';}
    result.push({
      id:uid(),
      time,
      customerId:borrower?.id||'',
      customerName:borrower?.name||'未知客户',
      channel:autopayChannels[Math.floor(Math.random()*autopayChannels.length)],
      loanId:loan.id,
      period:rep?.period||1,
      amount:rep?.amount||loan.amount/Math.max(1,loan.months)||1200,
      status,
      message,
      batch:`BATCH-${(1000+Math.floor(Math.random()*9000))}`,
      attempt: status==='成功'?1:(1+Math.floor(Math.random()*3))
    });
  }
  result.sort((a,b)=>b.time.localeCompare(a.time));
  return result;
}

function generateNotificationTasks(customers,templates){
  const tplList=(Array.isArray(templates) && templates.length)?templates:defaultMessageTemplates();
  const channels=['短信','企微','电话','APP Push'];
  const statuses=['排队','进行中','暂停','完成','失败'];
  const segments=['临期客户','高风险客群','VIP 复贷','沉默客户唤醒','M1 逾期','M2 法务对接'];
  const priorities=['高','中','低'];
  const tasks=[];
  for(let i=0;i<40;i++){
    const channel=channels[i%channels.length];
    const status=statuses[Math.floor(Math.random()*statuses.length)];
    const template=tplList[i%tplList.length];
    const target=80+Math.floor(Math.random()*260);
    const sent=Math.round(target*(0.4+Math.random()*0.6));
    const success=Math.round(sent*(0.6+Math.random()*0.3));
    const fail=Math.max(0,sent-success);
    const retries=Math.floor(Math.random()*4);
    const scheduledAt=new Date(Date.now()+((Math.floor(Math.random()*9)-4)*DAY_MS)+Math.random()*86400000);
    const createTime=addDays(scheduledAt,-1);
    tasks.push({
      id:'task-'+uid(),
      name:`${channel}批次 ${i+1}`,
      channel,
      segment:segments[i%segments.length],
      scheduleType:Math.random()>0.6?'循环':'一次性',
      scheduledAt:scheduledAt.toISOString(),
      status,
      targetCount:target,
      sentCount:sent,
      successCount:success,
      failCount:fail,
      retryCount:retries,
      templateId:template.id,
      owner:randomChineseName(),
      priority:priorities[Math.floor(Math.random()*priorities.length)],
      nextRetry:fail>0?addDays(new Date(),Math.floor(Math.random()*2)+1).toISOString():null,
      lastError:fail>0?'部分号码无效 / 用户拒接':'',
      timeline:[
        {time:createTime.toISOString(),event:'创建任务'},
        {time:scheduledAt.toISOString(),event:'进入排程计划'}
      ],
      notes:`已匹配 ${Math.floor(Math.random()*customers.length)} 名客户`
    });
  }
  return tasks;
}

/* 初始化演示数据：含多抵押物、沟通记录 */
function seed(){
  const roles=['客户经理','风控专员','合规','财务','催收','运营'];
  const statuses=['在职','试用','离职'];
  const depts=['福州一区','福州二区','闽侯支行','厦门分部','泉州分部','省级大客部'];
  const cities=['福州市','闽侯县','连江县','厦门市','泉州市','南平市','宁德市'];
  const streetWords=['东街','中路','海湾大道','鼓楼巷','江滨路','金山大道','湖滨北路','解放路','学院路','建新路'];
  const employees=[];
  for(let i=0;i<160;i++){
    employees.push({
      id:uid(),
      name:randomChineseName(),
      phone:randomPhone(),
      role:roles[Math.floor(Math.random()*roles.length)],
      status:statuses[Math.random()<0.8?0:Math.floor(Math.random()*statuses.length)]
    });
  }

  const wecomContacts = Array.from({length:200},(_,i)=>({
    id:`wc-${i+1}`,
    name:randomChineseName()+'·企微',
    dept:depts[i%depts.length],
    wechat:`wx_${Math.random().toString(36).slice(2,8)}`,
    mobile:randomPhone()
  }));

  const customers=[];
  for(let i=0;i<320;i++){
    const city=cities[i%cities.length];
    const customer={
      id:uid(),
      name:randomChineseName(),
      idcard:randomIdCard(),
      phone:randomPhone(),
      addr:`${city}${streetWords[i%streetWords.length]}${Math.ceil(Math.random()*200)}号`,
      attr:city,
      collaterals:generateCollaterals(),
      comms:[]
    };
    if(i%2===0){
      customer.wecomId = wecomContacts[i%wecomContacts.length].id;
    }
    customers.push(customer);
  }

  const configs={
    wecom:{corpid:'wx123',secret:'***',agent:'100001'},
    sms:{vendor:'Aliyun',key:'xxx',secret:'yyy',sign:'某小贷',tpl:'SMS_000'},
    autopay:{channel:'MockPay',merchant:'m_001',key:'k_001',notify:'https://example.com/callback'},
    reminder:{days:5,frequency:3}
  };
  const loans=[]; const repayments=[];
  customers.forEach((customer,idx)=>{
    if(idx%4===3) return; // 部分客户未放款，保持多样
    const amount = 80000 + Math.floor(Math.random()*12)*20000;
    const months = [12,18,24,36,48,60][Math.floor(Math.random()*6)];
    const rate = +(0.8+Math.random()*1.1).toFixed(2);
    const startDate = isoDate(addDays(new Date(),-Math.floor(Math.random()*365)));
    const status = Math.random()>0.15?'放款':'新增';
    const {loan,schedules}=createLoan({
      customerId:customer.id,
      amount,
      months,
      rateMonth:rate,
      startDate,
      status,
      collateralIds:(customer.collaterals.slice(0,1).map(c=>c.id))
    });
    loans.push(loan);
    repayments.push(...schedules);
    // 标记部分已还/逾期
    schedules.slice(0,Math.floor(Math.random()*months/2)).forEach((plan,planIdx)=>{
      plan.paid=true;
      plan.payDate=isoDate(addDays(plan.dueDate,Math.floor(Math.random()*3)));
    });
    if(Math.random()<0.2){
      const overduePlan=schedules.find(p=>!p.paid);
      if(overduePlan){
        overduePlan.dueDate=isoDate(addDays(new Date(),-Math.floor(Math.random()*30+1)));
      }
    }
  });
  const smsLogs=[];
  const wecomLogs=[];
  for(let i=0;i<50;i++){
    const borrower=customers[i%customers.length];
    const loan=loans[i%loans.length];
    if(!loan) break;
    const plans=repayments.filter(r=>r.loanId===loan.id);
    const rep=plans[i%Math.max(1,plans.length)];
    const time=new Date(Date.now()-i*3600*1000).toISOString();
    smsLogs.push({
      id:uid(),
      time,
      customerId:borrower.id,
      customerName:borrower.name,
      phone:borrower.phone,
      loanId:loan.id,
      period:rep?.period||i,
      amount:rep?.amount||1200,
      template:configs.sms.tpl,
      message:`第 ${rep?.period||i} 期应还 ${fmt(rep?.amount||1200)} 元，截止 ${rep?.dueDate||'2025-12-12'}`
    });
    wecomLogs.push({
      id:uid(),
      time,
      customerId:borrower.id,
      customerName:borrower.name,
      contactName:`${borrower.name}·企微`,
      wechat:`wx_${i}`,
      mobile:borrower.phone,
      loanId:loan.id,
      period:rep?.period||i,
      amount:rep?.amount||1200,
      message:`企微提醒：第 ${rep?.period||i} 期应还 ${fmt(rep?.amount||1200)} 元，截止 ${rep?.dueDate||'2025-12-12'}`
    });
  }
  const autopayLogs=generateAutopayLogs(customers,loans,repayments,configs);
  const messageTemplates=defaultMessageTemplates();
  const notificationTasks=generateNotificationTasks(customers,messageTemplates);
  return {employees,customers,loans,repayments,configs,wecomContacts,smsLogs,wecomLogs,autopayLogs,messageTemplates,notificationTasks};
}

function randomChineseName(){
  const family=['张','李','王','林','陈','蔡','郑','赵','黄','吴','周','许','邱','彭','叶'];
  const given=['安','博','晨','大海','东','晨曦','浩','嘉','康','凌','梦','楠','璇','潇','雅','泽','梓','悦','然','琪'];
  return family[Math.floor(Math.random()*family.length)]+given[Math.floor(Math.random()*given.length)];
}
function randomPhone(){
  return '13'+Math.floor(Math.random()*10)+String(Math.floor(Math.random()*1e8)).padStart(8,'0');
}
function randomIdCard(){
  return '3501'+String(Math.floor(Math.random()*1e10)).padStart(10,'0');
}
function generateCollaterals(){
  const count = 1 + Math.floor(Math.random()*3);
  const types=['住宅','商铺','车辆','动产','厂房'];
  const arr=[];
  for(let i=0;i<count;i++){
    arr.push({
      id:uid(),
      name:`${types[i%types.length]}抵押物 ${Math.ceil(Math.random()*500)}`,
      type:types[i%types.length],
      discount:+(6+Math.random()*4).toFixed(1),
      pledgeValue:200000+Math.floor(Math.random()*800000),
      houseCert:'闽房权证'+Math.floor(Math.random()*1e5),
      area:+(60+Math.random()*120).toFixed(1),
      note:''
    });
  }
  return arr;
}

function loadUIState(){
  try{
    const saved=localStorage.getItem(UI_STATE_KEY);
    if(saved){
      const parsed=JSON.parse(saved);
      Object.assign(uiState,parsed);
    }
  }catch(e){
    console.warn('UI state load failed',e);
  }
}
function persistUIState(){
  try{
    localStorage.setItem(UI_STATE_KEY, JSON.stringify(uiState));
  }catch(e){
    console.warn('UI state persist failed',e);
  }
}

const MockAPI = {
  fetchUpcomingRepayments(days=5){
    return new Promise((resolve)=>setTimeout(()=>resolve(countUpcomingReminders(days)),120));
  },
  fetchConfig(){
    return new Promise((resolve)=>setTimeout(()=>resolve(structuredClone(DB.configs)),60));
  }
};

function computeLoanOverdueCounts(){
  const now=new Date();
  const map={};
  for(const rep of DB.repayments){
    if(!rep.paid && new Date(rep.dueDate)<now){
      map[rep.loanId]=(map[rep.loanId]||0)+1;
    }
  }
  return map;
}

function computeCustomerOverdueCounts(loanMap){
  const map=new Map();
  for(const loan of DB.loans){
    const count=loanMap[loan.id]||0;
    if(count>0){
      map.set(loan.customerId,(map.get(loan.customerId)||0)+count);
    }
  }
  return map;
}

function countUpcomingReminders(days){
  const now=new Date();
  const limit=addDays(now, days);
  return DB.repayments.filter(r=>{
    if(r.paid) return false;
    const due=new Date(r.dueDate);
    return due>=now && due<=limit;
  }).length;
}

function filterLogsWithinDays(logs, days){
  const start=addDays(new Date(), -days);
  return (logs||[]).filter(log=>{
    if(!log.time) return false;
    return new Date(log.time)>=start;
  });
}

function computeCustomerLoanStats(customerId){
  const loans=DB.loans.filter(l=>l.customerId===customerId);
  let currentLoanAmount=0;
  let historyLoanAmount=0;
  let historyRepayAmount=0;
  let latestRepayDate=null;
  let finishDate=null;
  const loanIds=new Set(loans.map(l=>l.id));

  for(const loan of loans){
    const status=loanDerivedStatus(loan);
    if(['放款','逾期'].includes(status)){
      currentLoanAmount += loan.amount||0;
    }
    if(status==='完结'){
      historyLoanAmount += loan.amount||0;
    }
  }

  const repayments=DB.repayments.filter(r=>loanIds.has(r.loanId));
  for(const r of repayments){
    if(r.paid){
      historyRepayAmount += r.amount||0;
      if(r.payDate && (!latestRepayDate || r.payDate>latestRepayDate)){
        latestRepayDate = r.payDate;
      }
    }
  }

  for(const loan of loans){
    const scheds=repayments.filter(r=>r.loanId===loan.id);
    if(scheds.length>0 && scheds.every(item=>item.paid)){
      const doneDate=scheds.reduce((max,r)=>{
        if(!r.payDate) return max;
        return (!max || r.payDate>max)?r.payDate:max;
      }, null);
      if(doneDate && (!finishDate || doneDate>finishDate)){
        finishDate=doneDate;
      }
    }
  }

  return {
    currentLoanAmount,
    historyLoanAmount,
    historyRepayAmount,
    latestRepayDate,
    finishDate
  };
}

function computeAssetQualityMetrics(){
  const now=new Date();
  const unpaid=DB.repayments.filter(r=>!isInstallmentPaidAt(r,now));
  const totalOutstanding=unpaid.reduce((sum,item)=>sum+(item.amount||0),0);
  const thresholds=[1,7,30,60,90];
  const par=thresholds.map(days=>{
    const amount=unpaid.reduce((sum,item)=>sum+(calcDPD(item,now)>=days?(item.amount||0):0),0);
    const ratio=totalOutstanding?Math.min(1,amount/totalOutstanding):0;
    return {label:`PAR${days}`,amount,ratio};
  });
  const prevDate=addDays(now,-30);
  const rollBasePlans=unpaid.filter(plan=>{
    const prevBucket=calcDPD(plan,prevDate);
    return prevBucket>=1 && prevBucket<=30;
  });
  const rollBase=rollBasePlans.reduce((sum,p)=>sum+(p.amount||0),0);
  const rolled=rollBasePlans.reduce((sum,p)=>sum+(calcDPD(p,now)>30?(p.amount||0):0),0);

  const vintageMap=new Map();
  for(const loan of DB.loans){
    const cohort=(loan.startDate||'未知').slice(0,7);
    const plans=DB.repayments.filter(r=>r.loanId===loan.id);
    const outstanding=plans.filter(p=>!isInstallmentPaidAt(p,now)).reduce((sum,p)=>sum+(p.amount||0),0);
    const overdue=plans.filter(p=>!isInstallmentPaidAt(p,now)&&calcDPD(p,now)>=30).reduce((sum,p)=>sum+(p.amount||0),0);
    const nplAmt=plans.filter(p=>!isInstallmentPaidAt(p,now)&&calcDPD(p,now)>=90).reduce((sum,p)=>sum+(p.amount||0),0);
    const entry=vintageMap.get(cohort)||{cohort,count:0,disbursed:0,outstanding:0,overdue:0,npl:0};
    entry.count+=1;
    entry.disbursed+=loan.amount||0;
    entry.outstanding+=outstanding;
    entry.overdue+=overdue;
    entry.npl+=nplAmt;
    vintageMap.set(cohort,entry);
  }
  const vintageRows=[...vintageMap.values()].sort((a,b)=>b.cohort.localeCompare(a.cohort)).slice(0,6);

  const states=['新增','放款','逾期','完结','拒绝'];
  const matrix=states.map(()=>states.map(()=>0));
  DB.loans.forEach(loan=>{
    const prev=loanDerivedStatus(loan,prevDate);
    const curr=loanDerivedStatus(loan,now);
    const i=states.indexOf(prev);
    const j=states.indexOf(curr);
    if(i>=0 && j>=0) matrix[i][j]+=1;
  });

  const nplAmount=par[par.length-1]?.amount||0;
  return {
    totalOutstanding,
    par,
    npl:{amount:nplAmount,ratio:totalOutstanding?Math.min(1,nplAmount/totalOutstanding):0},
    roll:{base:rollBase,rolled,rate:rollBase?Math.min(1,rolled/rollBase):0},
    vintageRows,
    states,
    matrix
  };
}

function computeRevenueMetrics(){
  const now=new Date();
  const start30=addDays(now,-30);
  const unpaid=DB.repayments.filter(r=>!isInstallmentPaidAt(r,now));
  const outstandingPrincipal=DB.loans.reduce((sum,loan)=>sum+outstandingPrincipalOfLoan(loan.id),0);
  const receivableLast30=DB.repayments.filter(r=>{
    const due=new Date(r.dueDate);
    return due>=start30 && due<=now;
  }).reduce((sum,r)=>sum+(r.amount||0),0);
  const actualLast30=DB.repayments.filter(r=>{
    if(!r.payDate) return false;
    const pay=new Date(r.payDate);
    return pay>=start30 && pay<=now;
  }).reduce((sum,r)=>sum+(r.amount||0),0);
  const collectionRate=Math.min(1,receivableLast30?actualLast30/receivableLast30:1);
  const finishedLoans=DB.loans.filter(l=>loanDerivedStatus(l,now)==='完结');
  const prepayLoans=finishedLoans.filter(loan=>{
    const plans=DB.repayments.filter(r=>r.loanId===loan.id);
    if(!plans.length) return false;
    const lastDue=plans.reduce((max,r)=>{
      const due=new Date(r.dueDate);
      return (!max||due>max)?due:max;
    },null);
    const lastPay=plans.reduce((max,r)=>{
      if(!r.payDate) return max;
      const pay=new Date(r.payDate);
      return (!max||pay>max)?pay:max;
    },null);
    if(!lastDue || !lastPay) return false;
    return lastPay < lastDue;
  });
  const prepayRate=finishedLoans.length?Math.min(1,prepayLoans.length/finishedLoans.length):0;
  const totalDisbursed=DB.loans.reduce((sum,loan)=>sum+(loan.amount||0),0);
  const badDebtAmount=unpaid.filter(r=>calcDPD(r,now)>=120).reduce((sum,r)=>sum+(r.amount||0),0);
  const badDebtRate=totalDisbursed?Math.min(1,badDebtAmount/totalDisbursed):0;
  const overdueOutstanding=unpaid.filter(r=>calcDPD(r,now)>=30).reduce((sum,r)=>sum+(r.amount||0),0);
  const recoveredAmount=DB.repayments.filter(r=>{
    if(!r.paid || !r.payDate) return false;
    const dpdAtPay=Math.max(0,Math.floor((new Date(r.payDate)-new Date(r.dueDate))/DAY_MS));
    return dpdAtPay>30;
  }).reduce((sum,r)=>sum+(r.amount||0),0);
  const recoveryRate=(recoveredAmount+overdueOutstanding)?Math.min(1,recoveredAmount/(recoveredAmount+overdueOutstanding)):0;
  return {
    outstandingPrincipal,
    receivable:receivableLast30,
    actual:actualLast30,
    collectionRate,
    prepayRate,
    badDebtRate,
    recoveryRate
  };
}

function buildReminderHeatmapData(){
  const channels=['短信','企微','电话','APP Push'];
  const weekdays=['周一','周二','周三','周四','周五','周六','周日'];
  const matrix=channels.map(()=>Array(7).fill(0));
  const idxMap={1:0,2:1,3:2,4:3,5:4,6:5,0:6};
  const addCount=(channel,time,value=1)=>{
    if(!channel||!time) return;
    const row=channels.indexOf(channel);
    if(row<0) return;
    const d=new Date(time);
    if(Number.isNaN(d.getTime())) return;
    const col=idxMap[d.getDay()];
    if(col==null) return;
    matrix[row][col]+=value||0;
  };
  (DB.smsLogs||[]).forEach(log=>addCount('短信',log.time,1));
  (DB.wecomLogs||[]).forEach(log=>addCount('企微',log.time,1));
  (DB.notificationTasks||[]).forEach(task=>addCount(task.channel,task.scheduledAt,task.sentCount||task.targetCount||1));
  const max=Math.max(1,...matrix.flat());
  return {channels,weekdays,matrix,max};
}

function buildRadarDataset({reachRate,collectionRate,autopaySuccess,bindingRate,automationRate,riskControl}){
  const labels=['触达率','回款率','自动化代扣','企微绑定','任务运行','风险控制'];
  const clamp=v=>Math.max(0,Math.min(1,v||0));
  const data=[
    clamp(reachRate),
    clamp(collectionRate),
    clamp(autopaySuccess),
    clamp(bindingRate),
    clamp(automationRate),
    clamp(riskControl)
  ].map(v=>+(v*100).toFixed(1));
  return {labels,data};
}

function buildCohortRetention(){
  const periods=[30,60,90];
  const map=new Map();
  DB.loans.forEach(loan=>{
    const key=(loan.startDate||todayISO()).slice(0,7);
    if(!map.has(key)) map.set(key,{cohort:key,loans:[]});
    map.get(key).loans.push(loan);
  });
  const rows=[...map.values()].sort((a,b)=>b.cohort.localeCompare(a.cohort)).slice(0,6).map(item=>{
    const base=item.loans.length||1;
    const values=periods.map(days=>{
      const active=item.loans.filter(loan=>{
        const ref=addDays(new Date(loan.startDate||todayISO()),days);
        const status=loanDerivedStatus(loan,ref);
        return status!=='完结' && status!=='拒绝';
      }).length;
      return base?active/base:0;
    });
    return {cohort:item.cohort,base:item.loans.length,values};
  });
  return {periods,rows};
}

function buildOverdueFunnel(reminderCfg,smsRecent7,wecomRecent7,autopayRecentCount){
  const stageUpcoming=countUpcomingReminders(reminderCfg?.days||5);
  const manual=(DB.notificationTasks||[]).filter(t=>t.channel==='电话' && ['排队','进行中'].includes(t.status)).reduce((sum,t)=>sum+(t.targetCount||t.sentCount||0),0);
  const severe=DB.repayments.filter(r=>!r.paid && calcDPD(r,new Date())>=60).length;
  return [
    {label:'临期待提醒',value:stageUpcoming},
    {label:'已发送提醒',value:smsRecent7+wecomRecent7},
    {label:'自动化催收',value:autopayRecentCount},
    {label:'人工跟进',value:manual},
    {label:'法务处理',value:severe}
  ];
}

/* ===================== 工具函数 ===================== */
function isoDate(d){return new Date(d).toISOString().slice(0,10)}
function addMonths(d,n){const t=new Date(d);const o=new Date(t);o.setMonth(o.getMonth()+n);if(o.getDate()!==t.getDate())o.setDate(0);return o}
function addDays(d,n){const t=new Date(d);t.setDate(t.getDate()+n);return t}
function fmt(n){if(n==null||isNaN(n))return'-';return Number(n).toLocaleString('zh-CN',{maximumFractionDigits:2})}
function pct(n,digits=1){
  if(n==null||isNaN(n)) return '-';
  return `${(n*100).toFixed(digits)}%`;
}
function todayISO(){return isoDate(new Date())}
function qs(s,root=document){return root.querySelector(s)} function qsa(s,root=document){return Array.from(root.querySelectorAll(s))}
function val(sel){return qs(sel,dlg)?.value?.trim()||''} function toNum(sel){const v=parseFloat(val(sel));return isNaN(v)?0:v}
function formatDateTime(value){
  if(!value) return '-';
  const d=new Date(value);
  if(Number.isNaN(d.getTime())) return value;
  return d.toLocaleString('zh-CN',{hour12:false});
}
function formatShortDateTime(value){
  if(!value) return '-';
  const d=new Date(value);
  if(Number.isNaN(d.getTime())) return value;
  return d.toLocaleString('zh-CN',{month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'});
}
function isSameDay(a,b){
  if(!a||!b) return false;
  const da=new Date(a), db=new Date(b);
  if(Number.isNaN(da.getTime())||Number.isNaN(db.getTime())) return false;
  return da.getFullYear()===db.getFullYear() && da.getMonth()===db.getMonth() && da.getDate()===db.getDate();
}
function heatColor(value,max){
  if(!max || max<=0) return 'rgba(37,99,235,0.12)';
  const ratio=Math.max(0,Math.min(1,value/max));
  const alpha=(0.15+0.7*ratio).toFixed(2);
  return `rgba(37,99,235,${alpha})`;
}
function toInputDateTime(value){
  if(!value) return '';
  const d=new Date(value);
  if(Number.isNaN(d.getTime())) return '';
  const offset=d.getTimezoneOffset()*60000;
  return new Date(d.getTime()-offset).toISOString().slice(0,16);
}
function parseInputDateTime(value){
  if(!value) return null;
  const d=new Date(value);
  if(Number.isNaN(d.getTime())) return null;
  return d.toISOString();
}

/* ===================== 等额本息与计划 ===================== */
function calcAnnuity(P,rPercent,n){const r=rPercent/100;if(r<=0){const m=P/n;return{month:m,total:m*n,interest:0}}const pow=Math.pow(1+r,n);const m=P*r*pow/(pow-1);return{month:m,total:m*n,interest:m*n-P}}
function genSchedule(loan){const P=loan.amount,r=loan.rateMonth/100,n=loan.months;const pay=calcAnnuity(P,loan.rateMonth,n).month;let remain=P;const arr=[];for(let i=1;i<=n;i++){const interest=remain*r;const principal=Math.min(pay-interest,remain);remain=Math.max(0,remain-principal);arr.push({id:uid(),loanId:loan.id,period:i,dueDate:isoDate(addMonths(loan.startDate,i)),amount:+pay.toFixed(2),interest:+interest.toFixed(2),principal:+principal.toFixed(2),remain:+remain.toFixed(2),paid:false,payDate:null,remark:''});}return arr}

/* 创建贷款（“放款”状态时生成还款计划） */
function createLoan(payload){
  const loan={id:'L'+Date.now().toString(36).slice(-6),customerId:payload.customerId,amount:+payload.amount,months:+payload.months,rateMonth:+payload.rateMonth,startDate:payload.startDate||todayISO(),status:payload.status||'新增',note:payload.note||'',collateralIds:payload.collateralIds||[],comms:[]};
  const schedules=loan.status==='放款'?genSchedule(loan):[];
  return {loan,schedules};
}
function isInstallmentPaidAt(plan, refDate=new Date()){
  if(!plan) return false;
  if(!plan.paid) return false;
  if(!plan.payDate) return true;
  return new Date(plan.payDate)<=refDate;
}
function calcDPD(plan, refDate=new Date()){
  if(!plan || !plan.dueDate) return 0;
  if(isInstallmentPaidAt(plan, refDate)) return 0;
  const due=new Date(plan.dueDate);
  if(refDate<=due) return 0;
  return Math.max(0,Math.floor((refDate-due)/DAY_MS));
}
function loanDerivedStatus(loan,refDate=new Date()){
  const baseStatus=loan.status||'新增';
  if(baseStatus==='拒绝') return '拒绝';
  const plans=DB.repayments.filter(r=>r.loanId===loan.id);
  if(!plans.length) return baseStatus;
  const unpaid=plans.filter(p=>!isInstallmentPaidAt(p,refDate));
  if(unpaid.length===0) return '完结';
  const overdue=unpaid.some(p=>new Date(p.dueDate)<refDate);
  if(['新增','放款','逾期'].includes(baseStatus)){
    return overdue?'逾期':'放款';
  }
  if(baseStatus==='完结'){
    return overdue?'逾期':'完结';
  }
  return baseStatus;
}

/* ===================== 信用评估（0-100分） ===================== */
function outstandingPrincipalOfLoan(loanId){
  const arr=DB.repayments.filter(r=>r.loanId===loanId).sort((a,b)=>a.period-b.period);
  const firstUnpaid=arr.find(r=>!r.paid);
  if(!firstUnpaid) return 0;
  // 未还前的本金≈该期“本金+剩余本金”
  return (firstUnpaid.principal||0)+(firstUnpaid.remain||0);
}
function computeCustomerCredit(custId){
  const loans=DB.loans.filter(l=>l.customerId===custId);
  const plans=DB.repayments.filter(r=>loans.some(l=>l.id===r.loanId));
  const now=new Date(); const grace=3; // 3天宽限
  let overdueCnt=0, lateDays=0, lateItems=0, paidOnTime=0;
  for(const p of plans){
    const due=new Date(p.dueDate); const pay=p.payDate?new Date(p.payDate):null;
    const isLatePaid= p.paid && pay - due > (grace*86400000);
    const isOverdue= !p.paid && now - due > 0;
    if(isLatePaid){overdueCnt++; lateDays += Math.ceil((pay-due)/86400000)-grace; lateItems++}
    if(isOverdue){overdueCnt++; lateDays += Math.ceil((now-due)/86400000); lateItems++}
    if(p.paid && !isLatePaid) paidOnTime++;
  }
  const avgLate=lateItems? (lateDays/lateItems) : 0;
  const borrowed=loans.reduce((a,b)=>a+(b.amount||0),0);
  const outstanding=loans.reduce((a,b)=>a+outstandingPrincipalOfLoan(b.id),0);
  const ratio = borrowed>0 ? outstanding/borrowed : 0;

  // 评分规则（可按需要调整）
  let score=100;
  score -= overdueCnt*3;                // 每个逾期/滞后 -3
  score -= Math.min(30, avgLate*1);     // 平均滞后天数 *1，最多扣30
  score -= Math.round(Math.min(1,ratio)*20); // 未结清本金占比 *20
  score = Math.max(0, Math.min(100, Math.round(score)));

  let level='低风险';
  if(score<40) level='极高风险';
  else if(score<60) level='高风险';
  else if(score<80) level='中风险';

  return {score, level, stats:{loanCount:loans.length, borrowed, outstanding, overdueCnt, avgLate, ontime:paidOnTime}};
}
function creditAll(){
  const map=new Map();
  for(const c of DB.customers){ map.set(c.id, computeCustomerCredit(c.id)); }
  return map;
}

/* ===================== 报表中心 ===================== */
let chartCash,chartRiskBar,chartCashDelta,chartChannelPie,chartRadar,chartFunnel;
let dashboardSnapshot=null;
function renderDashboard(){
  const needsHome = qs('#m_due') || qs('#hero_date');
  const needsReports = qs('#c_cash') || qs('#asset_par_body') || qs('#risk_top') || qs('#channel_table');
  if(!needsHome && !needsReports) return;
  const now=new Date();const repay=DB.repayments;
  const unpaid=repay.filter(x=>!x.paid); const dueSum=unpaid.reduce((a,b)=>a+b.amount,0);
  const paidInterest=repay.filter(x=>x.paid).reduce((a,b)=>a+b.interest,0);
  const overdue=unpaid.filter(x=>new Date(x.dueDate)<now); const overdueSum=overdue.reduce((a,b)=>a+b.amount,0);
  const metricDue=qs('#m_due'); if(metricDue) metricDue.textContent=fmt(dueSum)+' 元';
  const metricInterest=qs('#m_interest'); if(metricInterest) metricInterest.textContent=fmt(paidInterest)+' 元';
  const metricOverdue=qs('#m_overdue'); if(metricOverdue) metricOverdue.textContent=fmt(overdueSum)+' 元';
  const heroDate=qs('#hero_date'); if(heroDate) heroDate.textContent = now.toLocaleString('zh-CN',{month:'long',day:'numeric',weekday:'long'});
  const activeLoansList=DB.loans.filter(l=>['放款','逾期'].includes(loanDerivedStatus(l)));
  const heroActiveLoans=qs('#hero_active_loans'); if(heroActiveLoans) heroActiveLoans.textContent = activeLoansList.length||0;
  const activeCustomers=new Set(activeLoansList.map(l=>l.customerId).filter(Boolean));
  const heroActiveCustomers=qs('#hero_active_customers'); if(heroActiveCustomers) heroActiveCustomers.textContent = activeCustomers.size||0;
  const monthStart=new Date(now.getFullYear(),now.getMonth(),1);
  const newLoanAmount=DB.loans.filter(l=>new Date(l.startDate)>=monthStart).reduce((sum,l)=>sum+(l.amount||0),0);
  const heroNewLoans=qs('#hero_new_loans'); if(heroNewLoans) heroNewLoans.textContent = fmt(newLoanAmount)+' 元';
  const heroOverdueRate=qs('#hero_overdue_rate'); if(heroOverdueRate) heroOverdueRate.textContent = pct(dueSum?overdueSum/dueSum:0);

  const cm=creditAll();
  const counts={低风险:0,中风险:0,高风险:0,极高风险:0};
  for(const v of cm.values()) counts[v.level]++;
  const riskHigh=qs('#m_risk_high'); if(riskHigh) riskHigh.textContent = (counts['高风险']+counts['极高风险'])||0;

  const byMonth={}; 
  for(const r of repay){
    const k=r.dueDate.slice(0,7);
    if(!byMonth[k]) byMonth[k]={plan:0,paid:0};
    byMonth[k].plan+=r.amount;
    if(r.paid) byMonth[k].paid+=r.amount;
  }
  const labels=Object.keys(byMonth).sort();
  labels.forEach((label,index)=>{
    const prevLabel=labels[index-1];
    if(prevLabel){
      byMonth[label].prevPaid = byMonth[prevLabel].paid;
    }else{
      byMonth[label].prevPaid = 0;
    }
  });
  const plan=labels.map(k=>+byMonth[k].plan.toFixed(2));
  const paids=labels.map(k=>+byMonth[k].paid.toFixed(2));
  const cashCanvas=qs('#c_cash');
  if(cashCanvas && typeof Chart!=='undefined'){
    if(chartCash)chartCash.destroy();
    chartCash=new Chart(cashCanvas,{type:'line',data:{labels,datasets:[{label:'应收',data:plan},{label:'实收',data:paids}]},options:{responsive:true,interaction:{mode:'index',intersect:false}}});
  }
  const cashDeltaCanvas=qs('#c_cash_delta');
  if(cashDeltaCanvas && typeof Chart!=='undefined'){
    if(chartCashDelta) chartCashDelta.destroy();
    const deltas=labels.map((label,index)=>{
      const monthData=byMonth[label];
      const prevPaid=byMonth[label].prevPaid||0;
      const prevPlan=labels[index-1]?byMonth[labels[index-1]].plan:0;
      return {
        netPaid:+(monthData.paid - prevPaid).toFixed(2),
        netPlan:+(monthData.plan - prevPlan).toFixed(2)
      };
    });
    chartCashDelta=new Chart(cashDeltaCanvas,{
      type:'bar',
      data:{
        labels,
        datasets:[
          {label:'净实收',data:deltas.map(d=>d.netPaid),backgroundColor:'#60a5fa'},
          {label:'净应收',data:deltas.map(d=>d.netPlan),backgroundColor:'#f59e0b'}
        ]
      },
      options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,title:{display:true,text:'金额'}}}}
    });
  }

  const riskCanvas=qs('#c_riskbar');
  if(riskCanvas && typeof Chart!=='undefined'){
    if(chartRiskBar)chartRiskBar.destroy();
    chartRiskBar=new Chart(riskCanvas,{
      type:'bar',
      data:{labels:Object.keys(counts),datasets:[{label:'客户数',data:Object.values(counts)}]},
      options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,precision:0}}}
    });
  }

  const riskTop=qs('#risk_top');
  if(riskTop){
    const rows=[...DB.customers].map(c=>({c,credit:cm.get(c.id)}))
      .sort((a,b)=>a.credit.score-b.credit.score).slice(0,5);
    riskTop.innerHTML = `
      <table>
        <thead><tr><th>客户</th><th>信用分</th><th>风险等级</th><th>逾期次数</th><th>平均滞后天</th><th>未结清本金</th></tr></thead>
        <tbody>${rows.map(r=>`<tr>
          <td>${r.c.name}</td><td><b>${r.credit.score}</b></td>
          <td><span class="tag ${r.credit.level.includes('高')?'err':(r.credit.level==='中风险'?'warn':'ok')}">${r.credit.level}</span></td>
          <td>${r.credit.stats.overdueCnt}</td>
          <td>${r.credit.stats.avgLate.toFixed(1)}</td>
          <td>${fmt(r.credit.stats.outstanding)}</td>
        </tr>`).join('')}</tbody></table>`;
  }

  const reminderCfg=DB.configs?.reminder||{days:5,frequency:3};
  const configMetric=qs('#m_config');
  if(configMetric){
    const upcoming=countUpcomingReminders(reminderCfg.days||5);
    configMetric.textContent = `${reminderCfg.days||0} 天 / 每日 ${reminderCfg.frequency||1} 次`;
    const configHelp=qs('#m_config_help');
    if(configHelp) configHelp.textContent = `未来 ${reminderCfg.days||0} 天内预计 ${upcoming} 人需提醒`;
  }
  let bindingRatio=0;
  const bindingMetric=qs('#m_wecom_rate');
  if(bindingMetric){
    const total=DB.customers.length||1;
    const bound=DB.customers.filter(c=>c.wecomId).length;
    bindingRatio=bound/total;
    bindingMetric.textContent = `${(bindingRatio*100).toFixed(1)}%`;
    const bindingHelp=qs('#m_wecom_help');
    if(bindingHelp) bindingHelp.textContent = `${bound} / ${DB.customers.length} 客户已完成企微绑定`;
  }
  let autopaySuccessRate=0;
  const autopayMetric=qs('#m_autopay_success');
  if(autopayMetric){
    const logs=(DB.autopayLogs||[]);
    const recent=logs.slice(0,30);
    const success=recent.filter(log=>log.status==='成功').length;
    autopaySuccessRate=recent.length?success/recent.length:0;
    autopayMetric.textContent=pct(autopaySuccessRate);
    const autopayHelp=qs('#m_autopay_help');
    if(autopayHelp){
      autopayHelp.textContent = recent.length?`近30条：成功${success} / 总${recent.length}`:'暂无代扣记录';
    }
  }
  const smsRecent7=filterLogsWithinDays(DB.smsLogs,7).length;
  const wecomRecent7=filterLogsWithinDays(DB.wecomLogs,7).length;
  const autopayRecent=filterLogsWithinDays(DB.autopayLogs,7);
  const autopayRecentCount=autopayRecent.length;
  const reminderTotal=smsRecent7 + wecomRecent7;
  const reminderMetric=qs('#m_reminder_total');
  if(reminderMetric){
    reminderMetric.textContent = reminderTotal;
    const reminderHelp=qs('#m_reminder_help');
    if(reminderHelp){
      reminderHelp.textContent = `短信 ${smsRecent7} / 企微 ${wecomRecent7} （近7日）`;
    }
  }
  const channelTable=qs('#channel_table');
  if(channelTable){
    const autopaySuccess=autopayRecent.filter(l=>l.status==='成功').length;
    const autopayRetry=autopayRecent.filter(l=>l.status==='重试').length;
    const autopayFail=autopayRecent.filter(l=>l.status==='失败').length;
    channelTable.innerHTML=`<table>
      <thead><tr><th>渠道</th><th>近7日数量</th><th>说明</th></tr></thead>
      <tbody>
        <tr><td>三方代扣</td><td>${autopayRecentCount}</td><td>成功 ${autopaySuccess} · 重试 ${autopayRetry} · 失败 ${autopayFail}</td></tr>
        <tr><td>短信提醒</td><td>${smsRecent7}</td><td>模板 ${DB.configs.sms?.tpl||'-'} · 签名 ${DB.configs.sms?.sign||'-'}</td></tr>
        <tr><td>企微通知</td><td>${wecomRecent7}</td><td>绑定客户 ${DB.customers.filter(c=>c.wecomId).length}</td></tr>
      </tbody>
    </table>`;
  }
  const channelPie=qs('#c_channel_pie');
  if(channelPie && typeof Chart!=='undefined'){
    if(chartChannelPie) chartChannelPie.destroy();
    chartChannelPie=new Chart(channelPie,{
      type:'doughnut',
      data:{
        labels:['短信','企微','三方代扣'],
        datasets:[{data:[smsRecent7,wecomRecent7,autopayRecentCount],backgroundColor:['#60a5fa','#f472b6','#34d399']}]
      },
      options:{responsive:true,plugins:{legend:{position:'bottom'}}}
    });
  }
  const heatmapGrid=qs('#heatmap_grid');
  const heatmapData=buildReminderHeatmapData();
  if(heatmapGrid){
    const headCells=heatmapData.weekdays.map(day=>`<th>${day}</th>`).join('');
    const bodyRows=heatmapData.channels.map((channel,rowIdx)=>{
      const cells=heatmapData.matrix[rowIdx].map(value=>`<td><span class="heat-cell" style="background:${heatColor(value,heatmapData.max)}">${value||0}</span></td>`).join('');
      return `<tr><th>${channel}</th>${cells}</tr>`;
    }).join('');
    heatmapGrid.innerHTML=`<table><thead><tr><th>渠道</th>${headCells}</tr></thead><tbody>${bodyRows}</tbody></table>`;
  }

  const assetQuality=computeAssetQualityMetrics();
  const parBody=qs('#asset_par_body');
  if(parBody){
    const rows=[
      ...assetQuality.par.map(item=>`<tr><td>${item.label}</td><td>${fmt(item.amount)}</td><td>${pct(item.ratio)}</td></tr>`),
      `<tr><td>NPL</td><td>${fmt(assetQuality.npl.amount)}</td><td>${pct(assetQuality.npl.ratio)}</td></tr>`,
      `<tr><td>滚动率 (1-30→30+)</td><td>${fmt(assetQuality.roll.rolled)}<div class="help">基数 ${fmt(assetQuality.roll.base)}</div></td><td>${pct(assetQuality.roll.rate)}</td></tr>`
    ];
    parBody.innerHTML=rows.join('');
    const assetTotal=qs('#asset_total');
    if(assetTotal){
      assetTotal.textContent = `未结清本息 ${fmt(assetQuality.totalOutstanding)} 元`;
    }
  }
  const vintageBody=qs('#vintage_body');
  if(vintageBody){
    if(assetQuality.vintageRows.length){
      vintageBody.innerHTML=assetQuality.vintageRows.map(row=>{
        const overdueRate=row.disbursed?row.overdue/row.disbursed:0;
        const nplRate=row.disbursed?row.npl/row.disbursed:0;
        return `<tr>
          <td>${row.cohort}</td>
          <td>${row.count}</td>
          <td>${fmt(row.disbursed)}</td>
          <td>${fmt(row.outstanding)}</td>
          <td>${pct(Math.min(1,overdueRate))}</td>
          <td>${pct(Math.min(1,nplRate))}</td>
        </tr>`;
      }).join('');
    }else{
      vintageBody.innerHTML='<tr><td colspan="6">暂无数据</td></tr>';}
  }
  const transitionTable=qs('#transition_table');
  if(transitionTable){
    const headCells=assetQuality.states.map(state=>`<th>${state}</th>`).join('');
    const bodyRows=assetQuality.states.map((from,rowIdx)=>{
      const cells=assetQuality.matrix[rowIdx].map(val=>`<td>${val}</td>`).join('');
      return `<tr><th>${from}</th>${cells}</tr>`;
    }).join('');
    transitionTable.innerHTML=`<thead><tr><th>上月 \ 本月</th>${headCells}</tr></thead><tbody>${bodyRows}</tbody>`;
  }

  const revenue=computeRevenueMetrics();
  const tasks=DB.notificationTasks||[];
  const automationRate=tasks.length?tasks.filter(t=>t.status==='进行中').length/tasks.length:0;
  const radarData=buildRadarDataset({
    reachRate:Math.min(1,reminderTotal/Math.max(DB.customers.length||1,1)),
    collectionRate:revenue.collectionRate,
    autopaySuccess:autopaySuccessRate,
    bindingRate:bindingRatio,
    automationRate,
    riskControl:Math.max(0,1-(assetQuality.npl.ratio||0))
  });
  const radarCanvas=qs('#c_radar');
  if(radarCanvas && typeof Chart!=='undefined'){
    if(chartRadar) chartRadar.destroy();
    chartRadar=new Chart(radarCanvas,{
      type:'radar',
      data:{labels:radarData.labels,datasets:[{label:'运营能力',data:radarData.data,backgroundColor:'rgba(96,165,250,0.25)',borderColor:'#2563eb',pointBackgroundColor:'#2563eb',pointBorderColor:'#2563eb'}]},
      options:{responsive:true,scales:{r:{beginAtZero:true,max:100,ticks:{display:false},grid:{color:'rgba(37,99,235,0.15)'}}}}
    });
  }
  const cohortData=buildCohortRetention();
  const cohortBody=qs('#cohort_body');
  if(cohortBody){
    if(cohortData.rows.length){
      cohortBody.innerHTML=cohortData.rows.map(row=>{
        const cells=row.values.map(val=>`<td><span class="heat-cell" style="background:${heatColor(val,1)}">${pct(val)}</span></td>`).join('');
        return `<tr><td>${row.cohort}</td><td>${row.base}</td>${cells}</tr>`;
      }).join('');
    }else{
      cohortBody.innerHTML=`<tr><td colspan="${cohortData.periods.length+2}">暂无数据</td></tr>`;
    }
  }
  const funnelData=buildOverdueFunnel(reminderCfg,smsRecent7,wecomRecent7,autopayRecentCount);
  const funnelCanvas=qs('#c_overdue_funnel');
  if(funnelCanvas && typeof Chart!=='undefined'){
    if(chartFunnel) chartFunnel.destroy();
    chartFunnel=new Chart(funnelCanvas,{
      type:'bar',
      data:{labels:funnelData.map(f=>f.label),datasets:[{label:'客户数',data:funnelData.map(f=>f.value),backgroundColor:['#34d399','#10b981','#38bdf8','#f97316','#ef4444'],borderRadius:12}]},
      options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}
    });
  }
  const kpiMap=[
    ['#k_inloan',fmt(revenue.outstandingPrincipal)+' 元'],
    ['#k_receivable',`${fmt(revenue.receivable)} / ${fmt(revenue.actual)}`],
    ['#k_collection',pct(revenue.collectionRate)],
    ['#k_prepay',pct(revenue.prepayRate)],
    ['#k_baddebt',pct(revenue.badDebtRate)],
    ['#k_recovery',pct(revenue.recoveryRate)]
  ];
  kpiMap.forEach(([sel,text])=>{
    const el=qs(sel);
    if(el) el.textContent=text;
  });

  dashboardSnapshot={
    metrics:{dueSum,paidInterest,overdueSum},
    reminder:{smsRecent7,wecomRecent7,autopayRecentCount},
    assetQuality,
    revenue,
    radar:radarData,
    heatmap:heatmapData,
    cohort:cohortData,
    funnel:funnelData,
    extra:{bindingRatio,autopaySuccessRate,automationRate,reminderTotal}
  };

}

/* ===================== 员工 ===================== */
function renderEmployees(){
  const input=qs('#e_q');
  const table=qs('#e_table');
  if(!table) return;
  const q=(input?.value||'').toLowerCase();
  const filtered=DB.employees.filter(x=>(x.name+x.phone+x.role+x.status).toLowerCase().includes(q));
  const {items,totalPages}=paginate(filtered,'employees');
  table.innerHTML=`<table><thead><tr><th>姓名</th><th>手机</th><th>角色</th><th>状态</th><th style="width:180px">操作</th></tr></thead>
  <tbody>${items.map(x=>`<tr><td>${x.name}</td><td>${x.phone}</td><td>${x.role||'-'}</td>
  <td><span class="tag ${x.status==='在职'?'ok':'warn'}">${x.status}</span></td>
  <td><button class="btn small" onclick='openEmployeeModal("${x.id}")'>编辑</button>
  <button class="btn small" onclick='delEmployee("${x.id}")'>删除</button></td></tr>`).join('')}</tbody></table>`;
  renderPagerControls('#e_page','employees',totalPages,renderEmployees);
}
const eSearch=qs('#e_q');
if(eSearch) eSearch.addEventListener('input',()=>{uiState.employees=1;persistUIState();renderEmployees();});
function openEmployeeModal(id){
  const isEdit=!!id; const d=isEdit?DB.employees.find(x=>x.id===id):{name:'',phone:'',role:'客户经理',status:'在职'};
  showDlg(isEdit?'编辑员工':'新增员工',`
    <div class="grid cols-2">
      <div><label>姓名</label><input id="f_name" class="input" value="${d.name||''}"></div>
      <div><label>手机</label><input id="f_phone" class="input" value="${d.phone||''}"></div>
      <div><label>角色</label><input id="f_role" class="input" value="${d.role||'客户经理'}"></div>
      <div><label>状态</label><select id="f_status" class="select">${['在职','试用','离职'].map(v=>`<option ${v===(d.status||'在职')?'selected':''}>${v}</option>`).join('')}</select></div>
    </div>`,()=>{
      const v={name:val('#f_name'),phone:val('#f_phone'),role:val('#f_role'),status:val('#f_status')};
      if(!v.name) return alert('姓名必填'); if(isEdit){Object.assign(d,v)}else{DB.employees.push({id:uid(),...v})} saveDB(); closeDlg();
  });
}
function delEmployee(id){if(confirm('确认删除该员工？')){DB.employees=DB.employees.filter(x=>x.id!==id);saveDB()}}

/* ===================== 客户（含信用分、档案、一键建贷） ===================== */
function renderCustomers(){
  const input=qs('#c_q');
  const table=qs('#c_table');
  if(!table) return;
  const q=(input?.value||'').toLowerCase();
  const cm=creditAll();
  const riskSelect=qs('#c_risk');
  if(riskSelect && uiState.customerRisk && riskSelect.value!==uiState.customerRisk){
    riskSelect.value = uiState.customerRisk;
  }
  const riskFilter=riskSelect?.value||'all';
  let filtered=DB.customers.filter(x=>(x.name+x.idcard+x.phone+(x.attr||'')+(x.addr||'')).toLowerCase().includes(q));
  if(riskFilter!=='all'){
    filtered=filtered.filter(c=>cm.get(c.id)?.level===riskFilter);
  }
  const loanOverdueMap=computeLoanOverdueCounts();
  const customerOverdueMap=computeCustomerOverdueCounts(loanOverdueMap);
  const {items,totalPages}=paginate(filtered,'customers');
  table.innerHTML=`<table><thead><tr>
    <th>姓名</th><th>身份证</th><th>电话</th><th>归宿地</th><th>抵押物(条)</th><th>企微客户</th><th>信用分</th><th>风险等级</th><th>在贷/历史</th><th>当前贷款金额</th><th>历史贷款金额</th><th>历史还款金额</th><th>最近还款日期</th><th>贷款完结日期</th><th>逾期次数</th><th style="width:360px">操作</th>
  </tr></thead><tbody>
  ${items.map(c=>{
    const loans=DB.loans.filter(l=>l.customerId===c.id);
    const active=loans.filter(l=>['放款','逾期'].includes(loanDerivedStatus(l))).length;
    const cr=cm.get(c.id);
    const wcName = c.wecomId ? (wecomContactById(c.wecomId)?.name||'-') : '未绑定';
    const wcTagClass = c.wecomId ? 'ok' : 'warn';
    const overdueCount = customerOverdueMap.get(c.id)||0;
    const stats = computeCustomerLoanStats(c.id);
    return `<tr><td>${c.name}</td><td>${c.idcard||'-'}</td><td>${c.phone||'-'}</td><td>${c.attr||'-'}</td>
      <td>${c.collaterals?.length||0}</td>
      <td><span class="tag ${wcTagClass}">${wcName}</span></td>
      <td><b>${cr.score}</b></td>
      <td><span class="tag ${cr.level.includes('高')?'err':(cr.level==='中风险'?'warn':'ok')}">${cr.level}</span></td>
      <td>${active}/${loans.length}</td>
      <td>${fmt(stats.currentLoanAmount)}</td>
      <td>${fmt(stats.historyLoanAmount)}</td>
      <td>${fmt(stats.historyRepayAmount)}</td>
      <td>${stats.latestRepayDate||'-'}</td>
      <td>${stats.finishDate||'-'}</td>
      <td>${overdueCount}</td>
      <td style="display:flex;flex-wrap:wrap;gap:6px;">
        <button class="btn small" onclick='openCustomerModal("${c.id}")'>编辑</button>
        <button class="btn small" onclick='openCustomerProfile("${c.id}")'>档案</button>
        <button class="btn small" onclick='openWecomBindModal("${c.id}")'>${c.wecomId?'更换企微':'绑定企微'}</button>
        ${c.wecomId?`<button class="btn small warn" onclick='unbindWecom("${c.id}")'>解绑</button>`:''}
        <button class="btn small" onclick='delCustomer("${c.id}")'>删除</button>
      </td></tr>`;
  }).join('')}</tbody></table>`;
  renderPagerControls('#c_page','customers',totalPages,renderCustomers);
}
const cSearch=qs('#c_q');
if(cSearch) cSearch.addEventListener('input',()=>{uiState.customers=1;persistUIState();renderCustomers();});
const cRisk=qs('#c_risk');
if(cRisk) {
  cRisk.value = uiState.customerRisk || 'all';
  cRisk.addEventListener('change',()=>{
    uiState.customerRisk=cRisk.value;
    uiState.customers=1;
    persistUIState();
    renderCustomers();
  });
}

function renderWecomContacts(){
  const table=qs('#wecom_list');
  if(!table) return;
  const search=(qs('#wc_q')?.value||'').toLowerCase();
  const deptSelect=qs('#wc_dept');
  if(deptSelect && uiState.wecomDept && deptSelect.value!==uiState.wecomDept){
    deptSelect.value=uiState.wecomDept;
  }
  const contacts=DB.wecomContacts||[];
  if(deptSelect && deptSelect.options.length<=1){
    const unique=[...new Set(contacts.map(c=>c.dept||'未分组'))];
    unique.forEach(dep=>{
      const option=document.createElement('option');
      option.value=dep;
      option.textContent=dep;
      deptSelect.appendChild(option);
    });
    deptSelect.value = uiState.wecomDept || 'all';
  }
  const deptValue=deptSelect?.value||'all';
  let filtered=contacts.slice();
  if(deptValue!=='all') filtered=filtered.filter(c=>(c.dept||'未分组')===deptValue);
  if(search) filtered=filtered.filter(c=>(c.name+c.wechat+c.mobile).toLowerCase().includes(search));
  const {items,totalPages}=paginate(filtered,'wecom');
  const rows=items.map(contact=>{
    const bound=DB.customers.filter(c=>c.wecomId===contact.id).map(c=>c.name).join('、') || '未绑定';
    return `<tr>
      <td>${contact.name}</td>
      <td>${contact.dept||'-'}</td>
      <td>${contact.wechat||'-'}</td>
      <td>${contact.mobile||'-'}</td>
      <td>${bound}</td>
    </tr>`;
  }).join('') || '<tr><td colspan="5">暂无数据</td></tr>';
  table.innerHTML=`<table><thead><tr><th>企微客户</th><th>所属团队</th><th>企微账号</th><th>手机</th><th>绑定借款人</th></tr></thead><tbody>${rows}</tbody></table>`;
  renderPagerControls('#wecom_page','wecom',totalPages,renderWecomContacts);
}
const wecomSearch=qs('#wc_q'); if(wecomSearch) wecomSearch.addEventListener('input',()=>{uiState.wecom=1;persistUIState();renderWecomContacts();});
const wecomDept=qs('#wc_dept'); if(wecomDept){
  wecomDept.value = uiState.wecomDept || 'all';
  wecomDept.addEventListener('change',()=>{
    uiState.wecomDept = wecomDept.value;
    uiState.wecom=1;
    persistUIState();
    renderWecomContacts();
  });
}
function renderSmsLogs(){
  const table=qs('#sms_table');
  if(!table) return;
  const input=qs('#sms_q');
  if(input && (input.value!== (uiState.smsQuery||''))){
    input.value = uiState.smsQuery||'';
  }
  const q=(input?.value||'').toLowerCase();
  let logs=(DB.smsLogs||[]).slice().sort((a,b)=>b.time.localeCompare(a.time));
  if(q){
    logs=logs.filter(log=>`${log.customerName||''}${log.phone||''}${log.loanId||''}${log.period||''}${log.message||''}`.toLowerCase().includes(q));
  }
  const {items,totalPages}=paginate(logs,'smsLogs');
  table.innerHTML=`<table><thead><tr><th>时间</th><th>客户</th><th>手机号</th><th>贷款号/期次</th><th>应还金额</th><th>内容</th></tr></thead>
    <tbody>${items.map(log=>`<tr>
      <td>${formatDateTime(log.time)}</td>
      <td>${log.customerName||'-'}</td>
      <td>${log.phone||'-'}</td>
      <td>${log.loanId||'-'} / 第${log.period||'-'}期</td>
      <td>${fmt(log.amount)}</td>
      <td>${log.message||''}</td>
    </tr>`).join('') || '<tr><td colspan="6">暂无短信记录</td></tr>'}</tbody></table>`;
  renderPagerControls('#sms_page','smsLogs',totalPages,renderSmsLogs);
}

function renderWecomNotifyLogs(){
  const table=qs('#wecom_logs');
  if(!table) return;
  const input=qs('#wecom_log_q');
  if(input && (input.value!== (uiState.wecomLogsQuery||''))){
    input.value = uiState.wecomLogsQuery||'';
  }
  const q=(input?.value||'').toLowerCase();
  let logs=(DB.wecomLogs||[]).slice().sort((a,b)=>b.time.localeCompare(a.time));
  if(q){
    logs=logs.filter(log=>`${log.customerName||''}${log.contactName||''}${log.wechat||''}${log.loanId||''}${log.message||''}`.toLowerCase().includes(q));
  }
  const {items,totalPages}=paginate(logs,'wecomLogs');
  table.innerHTML=`<table><thead><tr><th>时间</th><th>客户</th><th>企微联系人</th><th>企微/手机号</th><th>贷款号/期次</th><th>内容</th></tr></thead>
    <tbody>${items.map(log=>`<tr>
      <td>${formatDateTime(log.time)}</td>
      <td>${log.customerName||'-'}</td>
      <td>${log.contactName||'-'}</td>
      <td>${log.wechat||log.mobile||'-'}</td>
      <td>${log.loanId||'-'} / 第${log.period||'-'}期</td>
      <td>${log.message||''}</td>
    </tr>`).join('') || '<tr><td colspan="6">暂无企微通知记录</td></tr>'}</tbody></table>`;
  renderPagerControls('#wecom_log_page','wecomLogs',totalPages,renderWecomNotifyLogs);
}

function renderAutopayLogs(){
  const table=qs('#autopay_table');
  if(!table) return;
  const input=qs('#autopay_q');
  if(input && input.value!== (uiState.autopayQuery||'')){
    input.value = uiState.autopayQuery||'';
  }
  const q=(input?.value||'').toLowerCase();
  const statusSelect=qs('#autopay_status');
  if(statusSelect && statusSelect.value!== (uiState.autopayStatus||'all')){
    statusSelect.value = uiState.autopayStatus||'all';
  }
  let logs=(DB.autopayLogs||[]).slice().sort((a,b)=>b.time.localeCompare(a.time));
  if(q){
    logs=logs.filter(log=>`${log.customerName||''}${log.channel||''}${log.status||''}${log.loanId||''}${log.message||''}`.toLowerCase().includes(q));
  }
  const statusFilter=statusSelect?.value||'all';
  if(statusFilter!=='all'){
    logs=logs.filter(log=>log.status===statusFilter);
  }
  const {items,totalPages}=paginate(logs,'autopayLogs');
  table.innerHTML=`<table><thead><tr>
    <th>时间</th><th>客户</th><th>渠道</th><th>贷款号/期次</th><th>金额</th><th>状态</th><th>说明</th>
  </tr></thead><tbody>${items.map(log=>{
    const cls=log.status==='成功'?'ok':(log.status==='重试'?'warn':'err');
    return `<tr>
      <td>${formatDateTime(log.time)}</td>
      <td>${log.customerName||'-'}</td>
      <td>${log.channel||'-'}</td>
      <td>${log.loanId||'-'} / 第${log.period||'-'}期</td>
      <td>${fmt(log.amount)}</td>
      <td><span class="tag ${cls}">${log.status||'-'}</span></td>
      <td>${log.message||''}</td>
    </tr>`;
  }).join('') || '<tr><td colspan="7">暂无扣款记录</td></tr>'}</tbody></table>`;
  renderPagerControls('#autopay_page','autopayLogs',totalPages,renderAutopayLogs);
}
const cfgDaysInput=qs('#cfg_days'); if(cfgDaysInput) cfgDaysInput.addEventListener('input',updateSettingsPreview);
const cfgFreqInput=qs('#cfg_freq'); if(cfgFreqInput) cfgFreqInput.addEventListener('input',updateSettingsPreview);
const autopaySearchInput=qs('#autopay_q');
if(autopaySearchInput){
  autopaySearchInput.value=uiState.autopayQuery||'';
  autopaySearchInput.addEventListener('input',()=>{
    uiState.autopayQuery=autopaySearchInput.value;
    uiState.autopayLogs=1;
    persistUIState();
    renderAutopayLogs();
  });
}
const autopayStatusSelect=qs('#autopay_status');
if(autopayStatusSelect){
  autopayStatusSelect.value=uiState.autopayStatus||'all';
  autopayStatusSelect.addEventListener('change',()=>{
    uiState.autopayStatus=autopayStatusSelect.value;
    uiState.autopayLogs=1;
    persistUIState();
    renderAutopayLogs();
  });
}
const smsSearchInput=qs('#sms_q');
if(smsSearchInput){
  smsSearchInput.value = uiState.smsQuery||'';
  smsSearchInput.addEventListener('input',()=>{
    uiState.smsQuery=smsSearchInput.value;
    uiState.smsLogs=1;
    persistUIState();
    renderSmsLogs();
  });
}
const wecomLogSearch=qs('#wecom_log_q');
if(wecomLogSearch){
  wecomLogSearch.value = uiState.wecomLogsQuery||'';
  wecomLogSearch.addEventListener('input',()=>{
    uiState.wecomLogsQuery=wecomLogSearch.value;
    uiState.wecomLogs=1;
    persistUIState();
    renderWecomNotifyLogs();
  });
}
const notifyChannelSelect=qs('#notify_channel');
if(notifyChannelSelect){
  notifyChannelSelect.value=uiState.notificationChannel||'all';
  notifyChannelSelect.addEventListener('change',()=>{
    uiState.notificationChannel=notifyChannelSelect.value;
    uiState.notifications=1;
    persistUIState();
    renderNotificationCenter();
  });
}
const notifyStatusSelect=qs('#notify_status');
if(notifyStatusSelect){
  notifyStatusSelect.value=uiState.notificationStatus||'all';
  notifyStatusSelect.addEventListener('change',()=>{
    uiState.notificationStatus=notifyStatusSelect.value;
    uiState.notifications=1;
    persistUIState();
    renderNotificationCenter();
  });
}
const notifySearchInput=qs('#notify_q');
if(notifySearchInput){
  notifySearchInput.value=uiState.notificationSearch||'';
  notifySearchInput.addEventListener('input',()=>{
    uiState.notificationSearch=notifySearchInput.value;
    uiState.notifications=1;
    persistUIState();
    renderNotificationCenter();
  });
}
const tplSearchInput=qs('#tpl_search');
if(tplSearchInput){
  tplSearchInput.value=uiState.templateSearch||'';
  tplSearchInput.addEventListener('input',()=>{
    uiState.templateSearch=tplSearchInput.value;
    uiState.templates=1;
    persistUIState();
    renderNotificationTemplates();
  });
}

function addCollateralRow(container,data={}){
  const row=document.createElement('div'); row.className='grid cols-4'; row.style.border='1px dashed var(--line)'; row.style.padding='8px'; row.style.borderRadius='8px';
  row.innerHTML=`
    <div><label>名称/描述</label><input class="input c_name" value="${data.name||''}"></div>
    <div><label>类型</label><input class="input c_type" value="${data.type||''}" placeholder="房产/车辆/动产…"></div>
    <div><label>折价(折)</label><input class="input c_disc" value="${data.discount??''}"></div>
    <div><label>抵押价值(元)</label><input class="input c_value" value="${data.pledgeValue??''}"></div>
    <div><label>权证号</label><input class="input c_cert" value="${data.houseCert||''}"></div>
    <div><label>面积(㎡)</label><input class="input c_area" value="${data.area??''}"></div>
    <div style="grid-column:1/3"><label>备注</label><input class="input c_note" value="${data.note||''}"></div>
    <div style="display:flex;gap:6px;align-items:end;justify-content:end">
      <button class="btn small ok" type="button">一键建贷</button>
      <button class="btn small warn" type="button">移除</button>
    </div>
  `;
  const [btnCreate,btnDel]=row.querySelectorAll('button');
  btnDel.onclick=()=>row.remove();
  btnCreate.onclick=()=>quickCreateFromInline(row);
  container.appendChild(row);
}
function collectCollaterals(container){
  return Array.from(container.querySelectorAll('.grid.cols-4')).map(row=>({
    id:uid(), name:row.querySelector('.c_name').value.trim(),
    type:row.querySelector('.c_type').value.trim(),
    discount:parseFloat(row.querySelector('.c_disc').value)||0,
    pledgeValue:parseFloat(row.querySelector('.c_value').value)||0,
    houseCert:row.querySelector('.c_cert').value.trim(),
    area:parseFloat(row.querySelector('.c_area').value)||'',
    note:row.querySelector('.c_note').value.trim()
  })).filter(x=>x.name);
}

function openCustomerModal(id){
  const isEdit=!!id; const d=isEdit?structuredClone(DB.customers.find(x=>x.id===id)):{name:'',idcard:'',phone:'',addr:'',attr:'福州市',collaterals:[],comms:[]};
  showDlg((isEdit?'编辑':'新增')+'客户',`
    <div class="grid cols-3">
      <div><label>姓名</label><input id="f_name" class="input" value="${d.name||''}"></div>
      <div><label>身份证号</label><input id="f_idcard" class="input" value="${d.idcard||''}"></div>
      <div><label>电话</label><input id="f_phone" class="input" value="${d.phone||''}"></div>
      <div style="grid-column:1/3"><label>住址</label><input id="f_addr" class="input" value="${d.addr||''}"></div>
      <div><label>归宿地</label><select id="f_attr" class="select">${['福州市','闽侯县','连江县','罗源县','闽清县','永泰县'].map(v=>`<option ${v===(d.attr||'福州市')?'selected':''}>${v}</option>`).join('')}</select></div>
    </div>
    <div class="section-title">抵押物清单（可多条）</div>
    <div id="coll_box" class="grid" style="gap:8px"></div>
    <div><button class="btn small" type="button" id="btn_add_coll">+ 添加抵押物</button></div>
    <div class="help">提示：点击每条抵押物右侧的「一键建贷」可直接打开放款窗口并预选该抵押物。</div>
  `,()=>{
    const v={name:val('#f_name'),idcard:val('#f_idcard'),phone:val('#f_phone'),addr:val('#f_addr'),attr:val('#f_attr')};
    if(!v.name) return alert('姓名必填');
    const colls=collectCollaterals(qs('#coll_box',dlg));
    if(isEdit){
      const target=DB.customers.find(x=>x.id===id);
      Object.assign(target,v);
      const mapOld=new Map((target.collaterals||[]).map(x=>[x.name,x.id]));
      target.collaterals=colls.map(x=>({...x,id:mapOld.get(x.name)||x.id}));
    }else{
      DB.customers.push({id:uid(),...v,collaterals:colls,comms:[]});
    }
    saveDB(); closeDlg();
  });
  const box=qs('#coll_box',dlg); (d.collaterals||[]).forEach(c=>addCollateralRow(box,c));
  qs('#btn_add_coll',dlg).onclick=()=>addCollateralRow(box,{});
}
function quickCreateFromInline(row){
  // 在“编辑客户”弹窗中点击一键建贷：抽取当前行抵押物信息并打开新建放款
  const cName=row.querySelector('.c_name').value.trim();
  const value=parseFloat(row.querySelector('.c_value').value)||0;
  const disc=parseFloat(row.querySelector('.c_disc').value)||0;
  const estimate=Math.floor(value*(disc/10)*0.7); // 价值×折价×70%
  const customerName=val('#f_name');
  const customer=DB.customers.find(x=>x.name===customerName); // 简单匹配
  const collId=uid(); // 临时ID（保存客户后会以名称映射）
  openLoanModal(null,{customerId:customer?.id||'', collateralNames:[cName], amount:estimate});
}

/* ===================== 通知中心（统一消息任务） ===================== */
function notificationStatusClass(status){
  if(status==='进行中') return 'ok';
  if(status==='失败') return 'err';
  if(status==='暂停') return 'warn';
  if(status==='完成') return 'info';
  return 'info';
}
function renderNotificationCenter(){
  const mount=qs('#notify_table')||qs('#notify_schedule')||qs('#notify_total');
  if(!mount) return;
  const tasks=(DB.notificationTasks||[]).slice().sort((a,b)=> (b.scheduledAt||'').localeCompare(a.scheduledAt||''));
  const totalSent=tasks.reduce((sum,t)=>sum+(t.sentCount||0),0);
  const totalSuccess=tasks.reduce((sum,t)=>sum+(t.successCount||0),0);
  const summary=[
    ['#notify_total',tasks.length||0],
    ['#notify_running',tasks.filter(t=>t.status==='进行中').length||0],
    ['#notify_success',totalSent?`${(totalSuccess/Math.max(1,totalSent)*100).toFixed(1)}%`:'0%'],
    ['#notify_today',tasks.filter(t=>isSameDay(t.scheduledAt,new Date())).length||0]
  ];
  summary.forEach(([sel,val])=>{
    const el=qs(sel);
    if(el) el.textContent=val;
  });
  const channelSelect=qs('#notify_channel');
  if(channelSelect && channelSelect.value!==(uiState.notificationChannel||'all')){
    channelSelect.value=uiState.notificationChannel||'all';
  }
  const statusSelect=qs('#notify_status');
  if(statusSelect && statusSelect.value!==(uiState.notificationStatus||'all')){
    statusSelect.value=uiState.notificationStatus||'all';
  }
  const searchInput=qs('#notify_q');
  if(searchInput && searchInput.value!==(uiState.notificationSearch||'')){
    searchInput.value=uiState.notificationSearch||'';
  }
  let filtered=tasks.slice();
  if(uiState.notificationChannel && uiState.notificationChannel!=='all'){
    filtered=filtered.filter(t=>t.channel===uiState.notificationChannel);
  }
  if(uiState.notificationStatus && uiState.notificationStatus!=='all'){
    filtered=filtered.filter(t=>t.status===uiState.notificationStatus);
  }
  if(uiState.notificationSearch){
    const q=uiState.notificationSearch.toLowerCase();
    filtered=filtered.filter(t=>`${t.name||''}${t.channel||''}${t.segment||''}${t.owner||''}`.toLowerCase().includes(q));
  }
  const table=qs('#notify_table');
  if(table){
    const {items,totalPages}=paginate(filtered,'notifications');
    const rows=items.map(task=>{
      const tmpl=(DB.messageTemplates||[]).find(t=>t.id===task.templateId);
      const bar=Math.min(100,Math.round(((task.successCount||0)/Math.max(task.targetCount||1,1))*100));
      return `<tr>
        <td>${task.name}<div class="help">${task.segment||'-'} · ${task.owner||'-'}</div></td>
        <td>${task.channel||'-'}</td>
        <td>${task.scheduleType||'-'} · ${formatShortDateTime(task.scheduledAt)||'-'}</td>
        <td>
          目标 ${task.targetCount||0} · 已发 ${task.sentCount||0}
          <div class="progress-bar"><span style="width:${bar}%;"></span></div>
          <div class="help ok">成功 ${task.successCount||0}</div>
          <div class="help err">失败 ${task.failCount||0}</div>
        </td>
        <td><span class="tag ${notificationStatusClass(task.status)}">${task.status||'-'}</span></td>
        <td>${tmpl?.name||'-'}</td>
        <td style="display:flex;flex-wrap:wrap;gap:6px;">
          <button class="btn small" onclick='openNotificationModal("${task.id}")'>详情</button>
          <button class="btn small" onclick='triggerNotificationRetry("${task.id}")'>重试</button>
          <button class="btn small" onclick='toggleNotificationStatus("${task.id}")'>${task.status==='暂停'?'恢复':'暂停'}</button>
        </td>
      </tr>`;
    }).join('') || '<tr><td colspan="7">暂无任务</td></tr>';
    table.innerHTML=`<table><thead><tr><th>任务</th><th>渠道</th><th>计划</th><th>发送情况</th><th>状态</th><th>模板</th><th style="width:220px">操作</th></tr></thead><tbody>${rows}</tbody></table>`;
    renderPagerControls('#notify_pager','notifications',totalPages,renderNotificationCenter);
  }
  const scheduleBox=qs('#notify_schedule');
  if(scheduleBox){
    const upcoming=tasks.filter(t=>new Date(t.scheduledAt)>new Date()).slice(0,5);
    scheduleBox.innerHTML=upcoming.length?`<ul class="schedule-list">${upcoming.map(t=>`<li><b>${formatShortDateTime(t.scheduledAt)}</b> · ${t.channel||'-'} · ${t.name}<div class="help">目标 ${t.targetCount||0} · 状态 ${t.status}</div></li>`).join('')}</ul>`:'<div class="help">暂无即将执行的任务</div>';
  }
  const retryBox=qs('#notify_retry');
  if(retryBox){
    const failing=tasks.filter(t=>(t.failCount||0)>0 || t.status==='失败').slice(0,5);
    retryBox.innerHTML=failing.length?`<ul class="schedule-list">${failing.map(t=>`<li><b>${t.name}</b> · ${t.channel||'-'}<div class="help">失败 ${t.failCount||0} · 重试 ${t.retryCount||0} · 下一次 ${t.nextRetry?formatShortDateTime(t.nextRetry):'--'}</div></li>`).join('')}</ul>`:'<div class="help">暂无失败记录</div>';
  }
}

function renderNotificationTemplates(){
  const table=qs('#tpl_table');
  if(!table) return;
  const searchInput=qs('#tpl_search');
  if(searchInput && searchInput.value!==(uiState.templateSearch||'')){
    searchInput.value=uiState.templateSearch||'';
  }
  let list=(DB.messageTemplates||[]).slice().sort((a,b)=> (b.lastUsed||'').localeCompare(a.lastUsed||''));
  if(uiState.templateSearch){
    const q=uiState.templateSearch.toLowerCase();
    list=list.filter(t=>`${t.name||''}${t.channel||''}${t.category||''}${t.content||''}`.toLowerCase().includes(q));
  }
  const {items,totalPages}=paginate(list,'templates');
  const rows=items.map(tpl=>{
    const vars=Array.isArray(tpl.variables)?tpl.variables.join('、'):(tpl.variables||'-');
    return `<tr>
      <td>${tpl.name}</td>
      <td>${tpl.channel||'-'}</td>
      <td>${tpl.category||'-'}</td>
      <td>${vars||'-'}</td>
      <td>${(tpl.content||'').slice(0,40)}${(tpl.content||'').length>40?'…':''}</td>
      <td>${tpl.lastUsed?formatShortDateTime(tpl.lastUsed):'-'}</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap;">
        <button class="btn small" onclick='openTemplateModal("${tpl.id}")'>编辑</button>
        <button class="btn small" onclick='duplicateTemplate("${tpl.id}")'>复制</button>
        <button class="btn small" onclick='sendTemplateTest("${tpl.id}")'>测试</button>
      </td>
    </tr>`;
  }).join('') || '<tr><td colspan="7">暂无模板</td></tr>';
  table.innerHTML=`<table><thead><tr><th>名称</th><th>渠道</th><th>分类</th><th>变量</th><th>内容预览</th><th>最近使用</th><th style="width:180px">操作</th></tr></thead><tbody>${rows}</tbody></table>`;
  renderPagerControls('#tpl_pager','templates',totalPages,renderNotificationTemplates);
}

function openNotificationModal(id){
  const isEdit=!!id;
  const task=isEdit?DB.notificationTasks.find(t=>t.id===id):{name:'',channel:'短信',segment:'临期客户',scheduleType:'一次性',scheduledAt:new Date().toISOString(),status:'排队',targetCount:100,sentCount:0,successCount:0,failCount:0,templateId:(DB.messageTemplates?.[0]?.id||''),owner:randomChineseName(),priority:'中',notes:''};
  const tplOptions=(DB.messageTemplates||[]).map(t=>`<option value=\"${t.id}\" ${t.id===task.templateId?'selected':''}>${t.name}</option>`).join('');
  showDlg(isEdit?'通知任务详情':'新增通知任务',`
    <div class="grid cols-2">
      <div><label>任务名称</label><input id="nt_name" class="input" value="${task.name||''}"></div>
      <div><label>渠道</label><select id="nt_channel" class="select">${['短信','企微','电话','APP Push'].map(ch=>`<option ${ch===task.channel?'selected':''}>${ch}</option>`).join('')}</select></div>
      <div><label>受众标签</label><input id="nt_segment" class="input" value="${task.segment||''}"></div>
      <div><label>计划类型</label><select id="nt_schedule" class="select">${['一次性','循环','智能触发'].map(opt=>`<option ${opt===task.scheduleType?'selected':''}>${opt}</option>`).join('')}</select></div>
      <div><label>计划时间</label><input id="nt_time" class="input" type="datetime-local" value="${toInputDateTime(task.scheduledAt)||''}"></div>
      <div><label>模板</label><select id="nt_tpl" class="select">${tplOptions}</select></div>
      <div><label>目标人数</label><input id="nt_target" class="input" type="number" value="${task.targetCount||0}"></div>
      <div><label>负责人</label><input id="nt_owner" class="input" value="${task.owner||''}"></div>
      <div><label>优先级</label><select id="nt_priority" class="select">${['高','中','低'].map(p=>`<option ${p===task.priority?'selected':''}>${p}</option>`).join('')}</select></div>
      <div style="grid-column:1/3"><label>备注</label><textarea id="nt_note" class="input" style="width:100%;min-height:80px">${task.notes||''}</textarea></div>
    </div>
  `,()=>{
    const payload={
      name:val('#nt_name'),
      channel:val('#nt_channel'),
      segment:val('#nt_segment'),
      scheduleType:val('#nt_schedule'),
      scheduledAt:parseInputDateTime(val('#nt_time'))||new Date().toISOString(),
      templateId:val('#nt_tpl'),
      targetCount:parseInt(val('#nt_target'),10)||0,
      owner:val('#nt_owner'),
      priority:val('#nt_priority'),
      notes:val('#nt_note')
    };
    if(!payload.name) return alert('任务名称必填');
    if(isEdit){
      Object.assign(task,payload);
      task.timeline?.push({time:new Date().toISOString(),event:'任务已更新'});
    }else{
      DB.notificationTasks.unshift({id:'task-'+uid(),status:'排队',sentCount:0,successCount:0,failCount:0,retryCount:0,timeline:[{time:new Date().toISOString(),event:'创建任务'}],...payload});
    }
    saveDB();
    closeDlg();
  });
}

function toggleNotificationStatus(id){
  const task=DB.notificationTasks.find(t=>t.id===id);
  if(!task) return;
  task.status = task.status==='暂停' ? '排队' : (task.status==='完成'?'排队':'暂停');
  task.timeline?.push({time:new Date().toISOString(),event:`状态调整为 ${task.status}`});
  saveDB();
}

function triggerNotificationRetry(id){
  const task=DB.notificationTasks.find(t=>t.id===id);
  if(!task) return;
  task.retryCount=(task.retryCount||0)+1;
  const recovered=Math.min(task.failCount||0,Math.ceil((task.targetCount||0)*0.1));
  task.failCount=Math.max(0,(task.failCount||0)-recovered);
  task.successCount=(task.successCount||0)+recovered;
  task.status='进行中';
  task.timeline?.push({time:new Date().toISOString(),event:'触发失败重试'});
  saveDB();
  alert('已触发该任务的重试流程');
}

function simulateNotificationRun(){
  const pending=DB.notificationTasks.filter(t=>t.status==='排队').slice(0,3);
  if(!pending.length) return alert('暂无排队任务');
  pending.forEach(task=>{
    task.status='进行中';
    task.sentCount=task.targetCount;
    task.successCount=Math.round((task.targetCount||0)*(0.7+Math.random()*0.2));
    task.failCount=Math.max(0,(task.targetCount||0)-task.successCount);
    task.timeline?.push({time:new Date().toISOString(),event:'系统模拟执行完成'});
  });
  saveDB();
  alert(`已模拟执行 ${pending.length} 个任务`);
}

function downloadNotificationsCSV(){
  const rows=[['id','name','channel','segment','scheduleType','scheduledAt','status','target','sent','success','fail','template','owner','priority'],
    ...(DB.notificationTasks||[]).map(t=>[
      t.id,t.name,t.channel,t.segment,t.scheduleType,t.scheduledAt,t.status,t.targetCount||0,t.sentCount||0,t.successCount||0,t.failCount||0,t.templateId||'',t.owner||'',t.priority||''
    ])];
  const csv=rows.map(r=>r.map(x=>`\"${String(x??'').replace(/\"/g,'\"\"')}\"`).join(',')).join('\\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));a.download='notification_tasks.csv';a.click();
}

function openTemplateModal(id){
  const isEdit=!!id;
  const tpl=isEdit?DB.messageTemplates.find(t=>t.id===id):{channel:'短信',name:'',category:'催收',variables:['客户姓名','到期日'],content:'',retry:{max:1,gap:'--'}};
  const channels=['短信','企微','电话','APP Push'];
  showDlg(isEdit?'编辑模板':'新增模板',`
    <div class="grid cols-2">
      <div><label>模板名称</label><input id="tpl_name" class="input" value="${tpl.name||''}"></div>
      <div><label>渠道</label><select id="tpl_channel" class="select">${channels.map(ch=>`<option ${ch===tpl.channel?'selected':''}>${ch}</option>`).join('')}</select></div>
      <div><label>分类</label><input id="tpl_category" class="input" value="${tpl.category||''}"></div>
      <div><label>变量（逗号分隔）</label><input id="tpl_vars" class="input" value="${Array.isArray(tpl.variables)?tpl.variables.join(','):tpl.variables||''}"></div>
      <div><label>重试次数</label><input id="tpl_retry" class="input" type="number" value="${tpl.retry?.max??1}"></div>
      <div><label>间隔</label><input id="tpl_gap" class="input" value="${tpl.retry?.gap||'--'}"></div>
      <div style="grid-column:1/3"><label>模板内容</label><textarea id="tpl_content" class="input" style="width:100%;min-height:140px">${tpl.content||''}</textarea></div>
    </div>
  `,()=>{
    const payload={
      name:val('#tpl_name'),
      channel:val('#tpl_channel'),
      category:val('#tpl_category'),
      variables:(val('#tpl_vars')||'').split(/[,，]/).map(v=>v.trim()).filter(Boolean),
      content:val('#tpl_content'),
      retry:{max:parseInt(val('#tpl_retry'),10)||0,gap:val('#tpl_gap')||'--'},
      lastUsed:new Date().toISOString()
    };
    if(!payload.name) return alert('模板名称必填');
    if(isEdit){
      Object.assign(tpl,payload);
    }else{
      DB.messageTemplates.unshift({id:'tpl-'+uid(),...payload});
    }
    saveDB();
    closeDlg();
  });
}

function duplicateTemplate(id){
  const tpl=DB.messageTemplates.find(t=>t.id===id);
  if(!tpl) return;
  const copy=JSON.parse(JSON.stringify(tpl));
  DB.messageTemplates.unshift({...copy,id:'tpl-'+uid(),name:`${tpl.name}-副本`,lastUsed:null});
  saveDB();
  alert('模板已复制，可继续编辑。');
}

function sendTemplateTest(id){
  const tpl=DB.messageTemplates.find(t=>t.id===id);
  if(!tpl) return;
  alert(`已模拟向测试账号发送模板「${tpl.name}」`);
}

function downloadTemplatesCSV(){
  const rows=[['id','name','channel','category','variables','content','lastUsed'],
    ...(DB.messageTemplates||[]).map(t=>[
      t.id,t.name,t.channel||'',t.category||'',(Array.isArray(t.variables)?t.variables.join('|'):(t.variables||'')),t.content||'',t.lastUsed||''
    ])];
  const csv=rows.map(r=>r.map(x=>`\"${String(x??'').replace(/\"/g,'\"\"')}\"`).join(',')).join('\\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));a.download='message_templates.csv';a.click();
}

/* ===================== 客户档案与操作 ===================== */
function openCustomerProfile(id){
  const c=DB.customers.find(x=>x.id===id);
  const loans=DB.loans.filter(l=>l.customerId===id);
  const comms=[...(c.comms||[])]; loans.forEach(l=>(l.comms||[]).forEach(cm=>comms.push({...cm,loanId:l.id})));
  comms.sort((a,b)=>b.time.localeCompare(a.time));
  const credit=computeCustomerCredit(id);
  showDlg('客户档案 - '+c.name,`
    <div class="grid cols-3">
      <div class="form">
        <div class="section-title">基本信息</div>
        <div class="help">身份证：${c.idcard||'-'}<br>电话：${c.phone||'-'}<br>住址：${c.addr||'-'}<br>归宿地：${c.attr||'-'}</div>
        <div class="section-title">信用评估</div>
        <div>信用分：<b>${credit.score}</b>　等级：<span class="tag ${credit.level.includes('高')?'err':(credit.level==='中风险'?'warn':'ok')}">${credit.level}</span></div>
        <div class="help">逾期次数：${credit.stats.overdueCnt}，平均滞后：${credit.stats.avgLate.toFixed(1)} 天，未结清本金：${fmt(credit.stats.outstanding)} 元</div>
        <div style="margin-top:8px"><button class="btn small" type="button" onclick='addCustomerComm("${c.id}")'>+ 新增沟通记录</button></div>
      </div>
      <div class="form" style="grid-column:2/4">
        <div class="section-title">抵押物（点击右侧按钮一键创建贷款）</div>
        <table><thead><tr><th>名称</th><th>类型</th><th>折价</th><th>价值</th><th>操作</th></tr></thead>
        <tbody>${(c.collaterals||[]).map(x=>`<tr><td>${x.name}</td><td>${x.type||'-'}</td><td>${x.discount??'-'}</td><td>${fmt(x.pledgeValue)}</td>
          <td><button class="btn small ok" type="button" onclick='quickCreateLoan("${c.id}","${x.id}")'>用此抵押物一键建贷</button></td></tr>`).join('')}</tbody></table>
      </div>
    </div>
    <div class="form">
      <div class="section-title">放款记录 (${loans.length})</div>
      <table><thead><tr><th>贷款号</th><th>状态</th><th>金额/期数/利率</th><th>起始日</th><th>抵押物</th></tr></thead>
      <tbody>${loans.map(l=>`<tr><td>${l.id}</td><td><span class="tag ${loanDerivedStatus(l)==='逾期'?'err':loanDerivedStatus(l)==='放款'?'warn':'ok'}">${loanDerivedStatus(l)}</span></td>
        <td>${fmt(l.amount)} / ${l.months} / ${l.rateMonth}%</td><td>${l.startDate}</td>
        <td>${(l.collateralIds||[]).map(id=>c.collaterals.find(cc=>cc.id===id)?.name||id).join('，')||'-'}</td></tr>`).join('')}</tbody></table>
    </div>
    <div class="form">
      <div class="section-title">沟通记录（含贷款沟通）</div>
      <ul class="list-quiet">${comms.map(cm=>`<li><b>${cm.time}</b> ${cm.staff?`· ${cm.staff}`:''} ${cm.channel?`· ${cm.channel}`:''} ${cm.loanId?`· <span class="tag info">贷款 ${cm.loanId}</span>`:''}<br>${cm.content||''}</li>`).join('')||'<li class="help">暂无</li>'}</ul>
    </div>
  `,()=>closeDlg());
}
function addCustomerComm(cid){
  const c=DB.customers.find(x=>x.id===cid);
  showDlg('新增客户沟通 - '+c.name,`
    <div class="grid cols-3">
      <div><label>时间</label><input id="cm_time" class="input" type="datetime-local" value="${new Date().toISOString().slice(0,16)}"></div>
      <div><label>员工</label><input id="cm_staff" class="input" placeholder="如 张敏"></div>
      <div><label>渠道</label><input id="cm_ch" class="input" placeholder="电话/面谈/微信…"></div>
      <div style="grid-column:1/4"><label>内容</label><textarea id="cm_content" class="input" style="width:100%;min-height:120px"></textarea></div>
    </div>
  `,()=>{
    c.comms ||= [];
    c.comms.push({id:uid(),time:val('#cm_time')||new Date().toISOString(),staff:val('#cm_staff'),channel:val('#cm_ch'),content:val('#cm_content')});
    saveDB(); closeDlg();
  });
}
function delCustomer(id){
  if(!confirm('确认删除该客户？'))return;
  const loanIds=DB.loans.filter(l=>l.customerId===id).map(l=>l.id);
  DB.repayments=DB.repayments.filter(r=>!loanIds.includes(r.loanId));
  DB.loans=DB.loans.filter(l=>l.customerId!==id);
  DB.customers=DB.customers.filter(c=>c.id!==id);
  saveDB();
}

function openWecomBindModal(customerId){
  const customer = customerById(customerId);
  if(!customer) return;
  if(!DB.wecomContacts?.length) return alert('暂无企微客户数据');
  showDlg('绑定企微客户 - '+customer.name,`
    <label>选择企微客户</label>
    <select id="wc_select" class="select" style="width:100%;margin-bottom:10px;">
      ${DB.wecomContacts.map(wc=>`<option value="${wc.id}" ${wc.id===customer.wecomId?'selected':''}>${wc.name} (${wc.wechat||wc.mobile})</option>`).join('')}
    </select>
    <div class="help">绑定后可在逾期页面使用企微提醒。</div>
  `,()=>{
    const select=qs('#wc_select',dlg);
    if(!select?.value) return alert('请选择企微客户');
    customer.wecomId = select.value;
    saveDB();
    closeDlg();
  });
}

function unbindWecom(customerId){
  const customer = customerById(customerId);
  if(!customer) return;
  customer.wecomId = null;
  saveDB();
}

/* ===================== 放款（含一键建贷、抵押物预选、沟通） ===================== */
function customerById(id){return DB.customers.find(c=>c.id===id)}
function wecomContactById(id){return DB.wecomContacts?.find(c=>c.id===id)}
function wecomNameFor(c){const wc=wecomContactById(c?.wecomId);return wc?wc.name:'未绑定'}
function paginate(list,key,pageSize=PAGE_SIZE){
  const totalPages=Math.max(1,Math.ceil(list.length/ pageSize));
  const current=Math.min(Math.max(uiState[key]||1,1),totalPages);
  uiState[key]=current;
  persistUIState();
  const start=(current-1)*pageSize;
  return {items:list.slice(start,start+pageSize), page:current, totalPages};
}
function renderPagerControls(containerId,key,totalPages,renderFn){
  const container=qs(containerId);
  if(!container) return;
  if(totalPages<=1){container.innerHTML='';return;}
  const current=uiState[key]||1;
  container.innerHTML=`
    <div class="pagination">
      <button ${current===1?'disabled':''} data-action="first">首页</button>
      <button ${current===1?'disabled':''} data-action="prev">上一页</button>
      <span>${current} / ${totalPages}</span>
      <button ${current===totalPages?'disabled':''} data-action="next">下一页</button>
      <button ${current===totalPages?'disabled':''} data-action="last">末页</button>
    </div>`;
  container.querySelectorAll('button').forEach(btn=>{
    btn.onclick=()=>{
      if(btn.disabled) return;
      if(btn.dataset.action==='first') uiState[key]=1;
      else if(btn.dataset.action==='prev') uiState[key]=Math.max(1,(uiState[key]||1)-1);
      else if(btn.dataset.action==='next') uiState[key]=Math.min(totalPages,(uiState[key]||1)+1);
      else if(btn.dataset.action==='last') uiState[key]=totalPages;
      renderFn();
    };
  });
}

function renderLoanTabs(counts,currentStatus){
  const container=qs('#loan_tabs');
  if(!container) return;
  const labels={
    all:'全部',
    '放款':'放款',
    '完结':'完结',
    '逾期':'逾期'
  };
  container.innerHTML=loanStatuses.map(status=>{
    const label=labels[status]||status;
    const active=status===currentStatus;
    return `<button class="tab-btn ${active?'active':''}" data-status="${status}">${label} (${counts[status]||0})</button>`;
  }).join('');
  container.querySelectorAll('button').forEach(btn=>{
    btn.onclick=()=>{
      const status=btn.dataset.status;
      uiState.loanStatus=status;
      uiState.loans=1;
      persistUIState();
      renderLoans();
    };
  });
}
function renderLoans(){
  const input=qs('#l_q');
  const table=qs('#l_table');
  if(!table) return;
  const q=(input?.value||'').toLowerCase();
  const loanOverdueMap=computeLoanOverdueCounts();
  const baseList=DB.loans.map(l=>({...l,derived:loanDerivedStatus(l),overdueTimes:loanOverdueMap[l.id]||0}));
  const searchFiltered=baseList.filter(l=>(l.id+customerById(l.customerId)?.name||'').toLowerCase().includes(q));
  const counts={};
  loanStatuses.forEach(status=>{
    counts[status]=status==='all'?searchFiltered.length:searchFiltered.filter(x=>x.derived===status).length;
  });
  const currentStatus=uiState.loanStatus||'all';
  const tabsContainer=qs('#loan_tabs');
  if(tabsContainer){
    renderLoanTabs(counts,currentStatus);
  }
  const minInput=qs('#l_min');
  const maxInput=qs('#l_max');
  if(minInput && uiState.loanMin!==undefined && minInput.value!==String(uiState.loanMin||'')){
    minInput.value = uiState.loanMin || '';
  }
  if(maxInput && uiState.loanMax!==undefined && maxInput.value!==String(uiState.loanMax||'')){
    maxInput.value = uiState.loanMax || '';
  }
  const minAmount=minInput?.value?parseFloat(minInput.value):null;
  const maxAmount=maxInput?.value?parseFloat(maxInput.value):null;
  let filtered=currentStatus==='all'?searchFiltered:searchFiltered.filter(x=>x.derived===currentStatus);
  if(minAmount!=null) filtered=filtered.filter(l=>l.amount>=minAmount);
  if(maxAmount!=null) filtered=filtered.filter(l=>l.amount<=maxAmount);
  const {items,totalPages}=paginate(filtered,'loans');
  table.innerHTML=`<table><thead><tr>
    <th>当票号</th><th>客户</th><th>状态</th><th>金额/期数/利率</th><th>起始日</th><th>已还金额</th><th>是否亏损</th><th>盈利金额</th><th>逾期次数</th><th>抵押物</th><th style="width:380px">操作</th>
  </tr></thead><tbody>${items.map(l=>{
    const c=customerById(l.customerId); const coll=(l.collateralIds||[]).map(id=>c?.collaterals.find(cc=>cc.id===id)?.name||id).join('，')||'-';
    const s=loanDerivedStatus(l);
    const tagCls=s==='逾期'?'err':s==='放款'?'warn':(s==='拒绝'?'':'ok');
    const plans=DB.repayments.filter(r=>r.loanId===l.id);
    const paidAmount=plans.filter(p=>p.paid).reduce((sum,item)=>sum+item.amount,0);
    const profit=paidAmount - l.amount;
    const isLoss=paidAmount < l.amount;
    const lossLabel=isLoss?'是':'否';
    return `<tr><td>${l.id}</td><td>${c?.name||'-'}</td>
      <td><span class="tag ${tagCls}">${s}</span></td>
      <td>${fmt(l.amount)} / ${l.months} / ${l.rateMonth}%</td>
      <td>${l.startDate}</td>
      <td>${fmt(paidAmount)}</td>
      <td><span class="tag ${isLoss?'err':'ok'}">${lossLabel}</span></td>
      <td>${profit>0?fmt(profit):'-'}</td>
      <td>${l.overdueTimes||0}</td>
      <td>${coll}</td>
      <td>
        <button class="btn small" onclick='openLoanModal("${l.id}")'>编辑</button>
        <button class="btn small" onclick='openLoanComms("${l.id}")'>沟通记录</button>
        ${s==='新增'?`<button class="btn small ok" onclick='changeLoanStatus("${l.id}","放款")'>放款</button>
                     <button class="btn small" onclick='changeLoanStatus("${l.id}","拒绝")'>拒绝</button>`:''}
        ${s==='放款'||s==='逾期'?`<button class="btn small" onclick='changeLoanStatus("${l.id}","完结")'>完结</button>`:''}
        <button class="btn small warn" onclick='delLoan("${l.id}")'>删除</button>
      </td></tr>`;
  }).join('')}</tbody></table>`;
  renderPagerControls('#l_page','loans',totalPages,renderLoans);
}
const lSearch=qs('#l_q'); if(lSearch) lSearch.addEventListener('input',()=>{uiState.loans=1;persistUIState();renderLoans();});
const lMin=qs('#l_min'); if(lMin) {
  lMin.value = uiState.loanMin || '';
  lMin.addEventListener('input',()=>{
    uiState.loanMin = lMin.value;
    uiState.loans=1;
    persistUIState();
    renderLoans();
  });
}
const lMax=qs('#l_max'); if(lMax){
  lMax.value = uiState.loanMax || '';
  lMax.addEventListener('input',()=>{
    uiState.loanMax = lMax.value;
    uiState.loans=1;
    persistUIState();
    renderLoans();
  });
}

function renderLoanCollateralOptions(d){
  const sel=qs('#f_customer',dlg).value;
  const c=customerById(sel); const ids=new Set(d.collateralIds||[]);
  const box=qs('#f_coll_box',dlg);
  box.innerHTML=(c?.collaterals||[]).map(x=>`
    <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" value="${x.id}" ${ids.has(x.id)?'checked':''}/> ${x.name} <span class="help">(${x.type||'-'}/${fmt(x.pledgeValue)})</span></label>
  `).join('') || '<span class="help">该客户暂无抵押物</span>';
}

function openLoanModal(id, prefill={}){
  const isEdit=!!id;
  const base = isEdit
    ? structuredClone(DB.loans.find(x=>x.id===id))
    : {customerId: DB.customers[0]?.id||'', amount: 600000, months:60, rateMonth:1.2, startDate: todayISO(), status:'新增', note:'', collateralIds:[]};

  // 应用预填（来自一键建贷）
  if(prefill.customerId) base.customerId = prefill.customerId;
  if(prefill.collateralIds) base.collateralIds = prefill.collateralIds;
  if(prefill.collateralNames && !prefill.collateralIds){
    const c = DB.customers.find(x=>x.id===base.customerId);
    base.collateralIds = (prefill.collateralNames||[]).map(n=>c?.collaterals.find(cc=>cc.name===n)?.id).filter(Boolean);
  }
  if(prefill.amount) base.amount = prefill.amount;

  const calc=calcAnnuity(+base.amount,+base.rateMonth,+base.months);
  showDlg((isEdit?'编辑':'新建')+'放款',`
    <div class="grid cols-4">
      <div><label>客户</label><select id="f_customer" class="select">${DB.customers.map(c=>`<option value="${c.id}" ${c.id===base.customerId?'selected':''}>${c.name}</option>`).join('')}</select></div>
      <div><label>借款日期</label><input id="f_date" type="date" class="input" value="${base.startDate}"></div>
      <div><label>贷款金额(元)</label><input id="f_amount" class="input" value="${base.amount}"></div>
      <div><label>贷款月数</label><input id="f_months" class="input" value="${base.months}"></div>
      <div><label>月利率(%)</label><input id="f_rate" class="input" value="${base.rateMonth}"></div>
      <div><label>每月还款额</label><input id="f_monthpay" class="input" disabled value="${fmt(calc.month)}"></div>
      <div><label>总还款额</label><input id="f_total" class="input" disabled value="${fmt(calc.total)}"></div>
      <div><label>总利息</label><input id="f_interest" class="input" disabled value="${fmt(calc.interest)}"></div>
      <div><label>状态</label><select id="f_status" class="select">${['新增','拒绝','放款','完结'].map(v=>`<option ${v===(base.status||'新增')?'selected':''}>${v}</option>`).join('')}</select></div>
      <div style="grid-column:1/5"><label>选择抵押物（来自该客户）</label><div id="f_coll_box" class="grid" style="grid-template-columns:repeat(2,1fr)"></div></div>
    </div>
    <div style="margin-top:10px"><label>备注</label><input id="f_note" class="input" style="width:100%" value="${base.note||''}"></div>
    <div class="help" style="margin-top:6px">提示：保存为“放款”时将自动生成还款计划；“新增/拒绝”不生成计划；设为“完结”将清空未还并标记已结清。</div>
    <div style="margin-top:10px" class="row"><button type="button" class="btn" onclick="trialCalc()">试算</button><button type="button" class="btn" onclick="previewPlan()">预览还款计划</button></div>
  `,()=>{
    const payload={customerId:val('#f_customer'),startDate:val('#f_date'),amount:parseFloat(val('#f_amount')),months:parseInt(val('#f_months')),rateMonth:parseFloat(val('#f_rate')),status:val('#f_status'),note:val('#f_note')};
    payload.collateralIds=Array.from(qs('#f_coll_box',dlg).querySelectorAll('input[type=checkbox]:checked')).map(i=>i.value);
    if(!payload.customerId) return alert('请选择客户');
    if(!(payload.amount>0&&payload.months>0)) return alert('金额与期数需大于0');

    if(isEdit){
      const target=DB.loans.find(x=>x.id===base.id);
      Object.assign(target,payload);
      if(payload.status==='放款'){ if(!DB.repayments.some(r=>r.loanId===target.id)) DB.repayments.push(...genSchedule(target)); }
      else if(payload.status==='完结'){ DB.repayments.filter(r=>r.loanId===target.id && !r.paid).forEach(r=>{r.paid=true;r.payDate=todayISO();}); }
      else{ DB.repayments=DB.repayments.filter(r=>r.loanId!==target.id); }
    }else{
      const {loan,schedules}=createLoan(payload);
      DB.loans.push(loan); DB.repayments.push(...schedules);
    }
    saveDB(); closeDlg();
  });
  renderLoanCollateralOptions(base);
  qs('#f_customer',dlg).addEventListener('change',()=>renderLoanCollateralOptions({collateralIds:[]}));
}
// 从“客户档案”一键创建：预选客户与抵押物，并估算金额
function quickCreateLoan(customerId, collateralId){
  const c=customerById(customerId);
  const col=c?.collaterals.find(x=>x.id===collateralId);
  const estimate=Math.floor((col?.pledgeValue||0)*((col?.discount||0)/10)*0.7);
  openLoanModal(null,{customerId, collateralIds:[collateralId], amount: estimate});
}

function changeLoanStatus(id,status){
  const l=DB.loans.find(x=>x.id===id); if(!l) return;
  if(status==='放款' && !DB.repayments.some(r=>r.loanId===id)) DB.repayments.push(...genSchedule(l));
  if(status==='完结'){ DB.repayments.filter(r=>r.loanId===id && !r.paid).forEach(r=>{r.paid=true;r.payDate=todayISO();}); }
  if(status==='拒绝' || status==='新增'){ DB.repayments=DB.repayments.filter(r=>r.loanId!==id); }
  l.status=status; saveDB();
}

function openLoanComms(id){
  const l=DB.loans.find(x=>x.id===id); const c=customerById(l.customerId); l.comms ||= [];
  showDlg('沟通记录 - 贷款 '+l.id+' / '+(c?.name||''),`
    <div style="margin-bottom:8px"><button class="btn small" type="button" onclick='addLoanComm("${l.id}")'>+ 新增沟通记录</button></div>
    <ul class="list-quiet">${l.comms.slice().sort((a,b)=>b.time.localeCompare(a.time)).map(cm=>`<li><b>${cm.time}</b> ${cm.staff?`· ${cm.staff}`:''} ${cm.channel?`· ${cm.channel}`:''}<br>${cm.content||''}</li>`).join('')||'<li class="help">暂无沟通记录</li>'}</ul>
  `,()=>closeDlg());
}
function addLoanComm(id){
  const l=DB.loans.find(x=>x.id===id);
  showDlg('新增沟通 - '+l.id,`
    <div class="grid cols-3">
      <div><label>时间</label><input id="lc_time" class="input" type="datetime-local" value="${new Date().toISOString().slice(0,16)}"></div>
      <div><label>员工</label><input id="lc_staff" class="input"></div>
      <div><label>渠道</label><input id="lc_ch" class="input" placeholder="电话/面谈/微信…"></div>
      <div style="grid-column:1/4"><label>内容</label><textarea id="lc_content" class="input" style="width:100%;min-height:120px"></textarea></div>
    </div>
  `,()=>{l.comms ||= []; l.comms.push({id:uid(),time:val('#lc_time'),staff:val('#lc_staff'),channel:val('#lc_ch'),content:val('#lc_content')}); saveDB(); closeDlg();});
}

function delLoan(id){
  if(!confirm('确认删除该放款及其还款计划？'))return;
  DB.loans=DB.loans.filter(l=>l.id!==id); DB.repayments=DB.repayments.filter(r=>r.loanId!==id); saveDB();
}

/* ===================== 还款 / 逾期 ===================== */
function renderRepayments(){
  const input=qs('#r_q');
  const table=qs('#r_table');
  if(!table) return;
  const q=(input?.value||'').toLowerCase();
  const filterSelect=qs('#r_filter');
  if(filterSelect && uiState.repaymentFilter && filterSelect.value!==uiState.repaymentFilter){
    filterSelect.value=uiState.repaymentFilter;
  }
  const type=filterSelect?.value||'all';
  const now=new Date();
  let filtered=DB.repayments.map(r=>({...r,loan:DB.loans.find(l=>l.id===r.loanId),customer:customerById(DB.loans.find(l=>l.id===r.loanId)?.customerId)}));
  if(q) filtered=filtered.filter(x=>(x.loanId+(x.customer?.name||'')+x.period).toLowerCase().includes(q));
  if(type==='unpaid') filtered=filtered.filter(x=>!x.paid);
  if(type==='paid') filtered=filtered.filter(x=>x.paid);
  if(type==='overdue') filtered=filtered.filter(x=>!x.paid && new Date(x.dueDate)<now);
  const {items,totalPages}=paginate(filtered,'repayments');
  table.innerHTML=`<table><thead><tr><th>贷款号</th><th>客户</th><th>期次</th><th>到期</th><th>应还</th><th>本金</th><th>利息</th><th>状态</th><th>还款方式</th><th>操作时间</th><th style="width:240px">操作</th></tr></thead>
  <tbody>${items.map(x=>{const overdue=!x.paid&&new Date(x.dueDate)<now;const payLabel=x.paid?(x.payType==='autopay'?'三方代扣':'手动确认'):'-';const payTime=x.paid?formatDateTime(x.payMarkedAt||x.payDate):'-';return `<tr>
    <td>${x.loanId}</td><td>${x.customer?.name||'-'}</td><td>${x.period}</td><td>${x.dueDate}</td>
    <td>${fmt(x.amount)}</td><td>${fmt(x.principal)}</td><td>${fmt(x.interest)}</td>
    <td>${x.paid?'<span class="tag ok">已还</span>':(overdue?'<span class="tag err">逾期</span>':'<span class="tag warn">未还</span>')}</td>
    <td>${payLabel}</td>
    <td>${payTime}</td>
    <td>${x.paid?`<span class="help">还款日：${x.payDate}</span>`:`<div style="display:flex;flex-wrap:wrap;gap:6px;">
          <button class="btn small ok" onclick='markPaid("${x.id}")'>标记已还</button>
          <button class="btn small" onclick='smsOne("${x.id}")'>短信提醒</button>
          ${x.customer?.wecomId?`<button class="btn small" onclick='wecomPing("${x.id}")'>企微提醒</button>`:''}
          <button class="btn small" onclick='editRemark("${x.id}")'>备注</button>
        </div>`}</td></tr>`}).join('')}</tbody></table>`;
  renderPagerControls('#r_page','repayments',totalPages,renderRepayments);
}
const rSearch=qs('#r_q'); if(rSearch) rSearch.addEventListener('input',()=>{uiState.repayments=1;persistUIState();renderRepayments();});
const rFilter=qs('#r_filter'); if(rFilter){
  rFilter.value = uiState.repaymentFilter || 'all';
  rFilter.addEventListener('change',()=>{
    uiState.repaymentFilter = rFilter.value;
    uiState.repayments=1;
    persistUIState();
    renderRepayments();
  });
}
function markPaid(id, method='manual', options={}){
  const r=DB.repayments.find(x=>x.id===id);
  if(!r) return;
  if(r.paid) return;
  r.paid=true;
  r.payDate=todayISO();
  r.payType=method;
  r.payMarkedAt=new Date().toISOString();
  if(!options.silent){
    saveDB();
  }
}
function smsOne(id){
  const r=DB.repayments.find(x=>x.id===id);
  const loan=DB.loans.find(l=>l.id===r.loanId);
  const c=customerById(loan?.customerId);
  const message=`第 ${r.period} 期应还 ${fmt(r.amount)} 元，截止 ${r.dueDate}`;
  DB.smsLogs=DB.smsLogs||[];
  DB.smsLogs.unshift({
    id:uid(),
    time:new Date().toISOString(),
    customerId:c?.id||'',
    customerName:c?.name||'',
    phone:c?.phone||'',
    loanId:r.loanId,
    period:r.period,
    amount:r.amount,
    template:DB.configs.sms?.tpl||'',
    message
  });
  saveDB();
  alert(`[模拟短信] 向 ${c?.name||''}(${c?.phone||''}) 发送：${message}。签名：${DB.configs.sms.sign||'某小贷'}`);
}
function editRemark(id){const r=DB.repayments.find(x=>x.id===id);showDlg('备注',`<label>备注</label><textarea id="f_remark" class="input" style="width:100%;min-height:120px">${r.remark||''}</textarea>`,()=>{r.remark=val('#f_remark');saveDB();closeDlg()})}
function wecomPing(id){
  const r=DB.repayments.find(x=>x.id===id);
  if(!r) return;
  const loan=DB.loans.find(l=>l.id===r.loanId);
  const cust=customerById(loan?.customerId);
  if(!cust?.wecomId) return alert('该客户尚未绑定企微');
  const contact=wecomContactById(cust.wecomId);
  const message=`第 ${r.period} 期应还 ${fmt(r.amount)} 元，截止 ${r.dueDate}`;
  DB.wecomLogs=DB.wecomLogs||[];
  DB.wecomLogs.unshift({
    id:uid(),
    time:new Date().toISOString(),
    customerId:cust.id,
    customerName:cust.name,
    contactName:contact?.name||cust.name,
    wechat:contact?.wechat||'',
    mobile:contact?.mobile||cust.phone||'',
    loanId:r.loanId,
    period:r.period,
    amount:r.amount,
    message
  });
  saveDB();
  alert(`[企微提醒] 已向 ${contact?.name||cust.name}(${contact?.wechat||contact?.mobile||''}) 发送：${message}。`);
}

function renderOverdue(){
  const table=qs('#o_table');
  if(!table) return;
  const now=new Date(); const filtered=DB.repayments.filter(x=>!x.paid&&new Date(x.dueDate)<now).map(r=>({...r,customer:customerById(DB.loans.find(l=>l.id===r.loanId)?.customerId),days:Math.ceil((now-new Date(r.dueDate))/86400000)})).sort((a,b)=>b.days-a.days);
  const {items,totalPages}=paginate(filtered,'overdue');
  table.innerHTML=`<table><thead><tr><th>客户</th><th>贷款号</th><th>期次</th><th>到期</th><th>逾期天数</th><th>应还金额</th><th style="width:240px">操作</th></tr></thead>
  <tbody>${items.map(x=>`<tr><td>${x.customer?.name||'-'} / ${x.customer?.phone||''}</td><td>${x.loanId}</td><td>${x.period}</td><td>${x.dueDate}</td><td><b>${x.days}</b></td><td>${fmt(x.amount)}</td>
    <td style="display:flex;flex-wrap:wrap;gap:6px;">
      <button class="btn small" onclick='smsOne("${x.id}")'>短信提醒</button>
      ${x.customer?.wecomId?`<button class="btn small" onclick='wecomPing("${x.id}")'>企微提醒</button>`:''}
      <button class="btn small" onclick='editRemark("${x.id}")'>备注</button>
      <button class="btn small ok" onclick='markPaid("${x.id}")'>标记已还</button>
    </td></tr>`).join('')}</tbody></table>`;
  renderPagerControls('#o_page','overdue',totalPages,renderOverdue);
}
function bulkSMS(){const now=new Date();const ids=DB.repayments.filter(x=>!x.paid&&new Date(x.dueDate)<now).map(x=>x.id);if(!ids.length)return alert('暂无逾期');ids.slice(0,30).forEach(id=>smsOne(id))}

/* ===================== 三方配置 & 导出 ===================== */
function fillConfigs(){
  const c=DB.configs||{};
  const wx=qs('#wx_corpid');
  if(wx){
    wx.value=c.wecom?.corpid||'';
    qs('#wx_secret').value=c.wecom?.secret||'';
    qs('#wx_agent').value=c.wecom?.agent||'';
    qs('#sms_vendor').value=c.sms?.vendor||'Aliyun';
    qs('#sms_key').value=c.sms?.key||'';
    qs('#sms_secret').value=c.sms?.secret||'';
    qs('#sms_sign').value=c.sms?.sign||'';
    qs('#sms_tpl').value=c.sms?.tpl||'';
    qs('#ap_channel').value=c.autopay?.channel||'MockPay';
    qs('#ap_merchant').value=c.autopay?.merchant||'';
    qs('#ap_key').value=c.autopay?.key||'';
    qs('#ap_notify').value=c.autopay?.notify||'';
  }
  const daysInput=qs('#cfg_days');
  const freqInput=qs('#cfg_freq');
  if(daysInput && freqInput){
    daysInput.value=c.reminder?.days ?? 5;
    freqInput.value=c.reminder?.frequency ?? 3;
    updateSettingsPreview();
  }
}
function saveConfig(which){const c=DB.configs;if(which==='wecom')c.wecom={corpid:qs('#wx_corpid').value,secret:qs('#wx_secret').value,agent:qs('#wx_agent').value};
if(which==='sms')c.sms={vendor:qs('#sms_vendor').value,key:qs('#sms_key').value,secret:qs('#sms_secret').value,sign:qs('#sms_sign').value,tpl:qs('#sms_tpl').value};
if(which==='autopay')c.autopay={channel:qs('#ap_channel').value,merchant:qs('#ap_merchant').value,key:qs('#ap_key').value,notify:qs('#ap_notify').value}; saveDB(); alert('已保存')}
function testWeCom(){alert(`[模拟] 已从企业微信同步客户与标签，CorpID=${DB.configs.wecom?.corpid||'-'}`)}
function mockSendOne(){const c=DB.customers[0];if(!c)return alert('暂无客户');alert(`[模拟短信] 发送给 ${c.name}(${c.phone})，模板 ${DB.configs.sms.tpl||'-'}，签名 ${DB.configs.sms.sign||'-'}`)}
function mockDeduct(){
  const channel=DB.configs.autopay?.channel||'Mock';
  const pending=DB.repayments.filter(r=>!r.paid).sort((a,b)=>new Date(a.dueDate)-new Date(b.dueDate)).slice(0,5);
  if(!pending.length) return alert('暂无待代扣计划');
  DB.autopayLogs=DB.autopayLogs||[];
  const statusPool=['成功','重试','失败'];
  pending.forEach((rep,index)=>{
    const status=statusPool[Math.floor(Math.random()*statusPool.length)];
    const loan=DB.loans.find(l=>l.id===rep.loanId);
    const cust=customerById(loan?.customerId);
    const log={
      id:uid(),
      time:new Date().toISOString(),
      customerId:cust?.id||'',
      customerName:cust?.name||'',
      channel,
      loanId:rep.loanId,
      period:rep.period,
      amount:rep.amount,
      status,
      message:'',
    };
    if(status==='成功'){
      log.message='代扣成功，资金已入账';
      markPaid(rep.id,'autopay',{silent:true});
    }else if(status==='重试'){
      log.message='通道响应重试，等待下一次代扣';
    }else{
      log.message='代扣失败，请人工跟进';
    }
    DB.autopayLogs.unshift(log);
  });
  saveDB();
  alert(`[模拟代扣] 通道 ${channel} 已处理 ${pending.length} 条，详情见扣款记录页面。`);
}

function downloadCSV(which){
  let rows=[];
  if(which==='employees'){rows=[['id','name','phone','role','status'],...DB.employees.map(e=>[e.id,e.name,e.phone,e.role,e.status])]}
  else if(which==='customers'){
    const cm=creditAll();
    rows=[['id','name','idcard','phone','addr','attr','collaterals_count','credit_score','risk_level'],
      ...DB.customers.map(c=>[c.id,c.name,c.idcard,c.phone,c.addr,c.attr,c.collaterals?.length||0,cm.get(c.id).score,cm.get(c.id).level])]
  }else if(which==='loans'){
    rows=[['id','customer','status','amount','months','rateMonth','startDate','collaterals'],
      ...DB.loans.map(l=>[l.id,customerById(l.customerId)?.name||'',loanDerivedStatus(l),l.amount,l.months,l.rateMonth,l.startDate,(l.collateralIds||[]).join('|')])]
  }else if(which==='repayments'){
    rows=[['id','loanId','period','dueDate','amount','principal','interest','remain','paid','payDate','remark'],...DB.repayments.map(r=>[r.id,r.loanId,r.period,r.dueDate,r.amount,r.principal,r.interest,r.remain,r.paid,r.payDate||'',r.remark||''])];
  }
  const csv=rows.map(r=>r.map(x=>`"${String(x??'').replace(/"/g,'""')}"`).join(',')).join('\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));a.download=which+'.csv';a.click();
}

function downloadWecomCSV(){
  const rows=[['id','name','dept','wechat','mobile','bound_customers'],
    ...(DB.wecomContacts||[]).map(c=>[
      c.id,
      c.name,
      c.dept||'',
      c.wechat||'',
      c.mobile||'',
      DB.customers.filter(customer=>customer.wecomId===c.id).map(cu=>cu.name).join('|')
    ])];
  const csv=rows.map(r=>r.map(x=>`"${String(x??'').replace(/"/g,'""')}"`).join(',')).join('\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));a.download='wecom_contacts.csv';a.click();
}

function exportDashboardExcel(){
  if(!dashboardSnapshot) return alert('请先打开报表中心页面以生成数据');
  const snap=dashboardSnapshot;
  const rows=[['类别','指标','值'],
    ['基础','待收款(元)',fmt(snap.metrics.dueSum)],
    ['基础','已收利息(元)',fmt(snap.metrics.paidInterest)],
    ['基础','逾期金额(元)',fmt(snap.metrics.overdueSum)],
    ['提醒','短信近7日',snap.reminder.smsRecent7||0],
    ['提醒','企微近7日',snap.reminder.wecomRecent7||0],
    ['提醒','三方代扣近7日',snap.reminder.autopayRecentCount||0],
    ['资产质量','NPL金额(元)',fmt(snap.assetQuality.npl.amount)],
    ['资产质量','NPL占比',pct(snap.assetQuality.npl.ratio)],
    ['资产质量','滚动率',pct(snap.assetQuality.roll.rate)],
    ['收益','在贷余额(元)',fmt(snap.revenue.outstandingPrincipal)],
    ['收益','近30天应收(元)',fmt(snap.revenue.receivable)],
    ['收益','近30天实收(元)',fmt(snap.revenue.actual)],
    ['收益','回款率',pct(snap.revenue.collectionRate)],
    ['收益','提前结清率',pct(snap.revenue.prepayRate)],
    ['收益','坏账率',pct(snap.revenue.badDebtRate)],
    ['收益','回收率',pct(snap.revenue.recoveryRate)]
  ];
  snap.assetQuality.par.forEach(item=>rows.push(['资产质量',item.label,pct(item.ratio)]));
  if(Array.isArray(snap.cohort?.rows)){
    snap.cohort.rows.forEach(row=>{
      snap.cohort.periods.forEach((period,idx)=>{
        rows.push(['队列分析',`${row.cohort} ${period}天保留`,pct(row.values[idx]||0)]);
      });
    });
  }
  if(Array.isArray(snap.funnel)){
    snap.funnel.forEach(stage=>rows.push(['逾期漏斗',stage.label,stage.value]));
  }
  const csv=rows.map(r=>r.map(x=>`"${String(x??'').replace(/"/g,'""')}"`).join(',')).join('\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));a.download='dashboard_report.csv';a.click();
}

function exportDashboardPDF(){
  window.print();
}

function saveSettings(){
  const daysInput=qs('#cfg_days');
  const freqInput=qs('#cfg_freq');
  if(!daysInput || !freqInput) return;
  const days=parseInt(daysInput.value,10);
  const frequency=parseInt(freqInput.value,10);
  if(!(days>0)) return alert('临期天数需大于0');
  if(!(frequency>0)) return alert('临期提醒频率需大于0');
  DB.configs.reminder={days, frequency};
  saveDB();
  alert('已保存临期配置');
}

function resetSettings(){
  DB.configs.reminder={days:5,frequency:3};
  saveDB();
  fillConfigs();
  alert('已恢复默认值');
}

function updateSettingsPreview(){
  const summary=qs('#cfg_summary');
  const example=qs('#cfg_example');
  if(!summary || !example) return;
  const days=parseInt(qs('#cfg_days')?.value,10) || DB.configs.reminder?.days || 5;
  const frequency=parseInt(qs('#cfg_freq')?.value,10) || DB.configs.reminder?.frequency || 1;
  summary.textContent='统计中...';
  example.textContent='示例提醒文案计算中...';
  MockAPI.fetchUpcomingRepayments(days).then(count=>{
    summary.textContent=`未来 ${days} 天内共有 ${count} 名客户即将到期，计划每日提醒 ${frequency} 次`;
    const totalMessages = count * frequency;
    example.textContent=`示例：通过企微/短信每天推送 ${totalMessages} 条提醒，直至客户完成还款。`;
  });
}

/* ===================== 对话框/导航/刷新 ===================== */
const dlg=qs('#dlg');
function showDlg(title,html,onok){
  if(!dlg) return alert('当前页面暂不支持此操作');
  qs('#dlg_title').textContent=title;qs('#dlg_body').innerHTML=html;
  qs('#dlg_ok').onclick=(e)=>{e.preventDefault();onok&&onok()};
  dlg.showModal();
}
function closeDlg(){dlg?.close()}
function resetDemo(){if(confirm('将清空并写入演示数据，确定吗？')){localStorage.removeItem(KEY);DB=seed();refreshAll()}}
function refreshAll(){renderDashboard();renderEmployees();renderCustomers();renderLoans();renderRepayments();renderOverdue();renderWecomContacts();renderSmsLogs();renderWecomNotifyLogs();renderAutopayLogs();renderNotificationCenter();renderNotificationTemplates();fillConfigs()}
if(document.readyState!=='loading') refreshAll();
else document.addEventListener('DOMContentLoaded',refreshAll);
