export default function(component){
  const root=component.parentElement;
  const tabs=[...root.querySelectorAll('.tab')];
  tabs.forEach((tab,index)=>tab.addEventListener('click',()=>{
    tabs.forEach((t,i)=>t.classList.toggle('active',i===index));
    component.setStateValue('mock_step',index+1);
  }));
  root.querySelectorAll('.mix-row input[type="checkbox"]').forEach(cb=>{
    cb.addEventListener('change',()=>component.setStateValue('mock_mixer_event',String(Date.now())));
  });
}
