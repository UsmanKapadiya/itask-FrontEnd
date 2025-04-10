import axios from 'axios';
import Cookies from 'js-cookie';

const instance = axios.create({
  baseURL: `http://backend.itask.intelligrp.com/`,
  timeout: 50000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    
  },
});

// Add a request interceptor
instance.interceptors.request.use(function (config) {
  // Do something before request is sent
  let token;
  if (Cookies.get('userToken')) {
    token = JSON.parse(Cookies.get('userToken'));
  }

  let company;

  if (Cookies.get('company')) {
    company = Cookies.get('company');
  }

  // console.log('Admin Http Services Cookie Read : ' + company);
  // let companyName = JSON.stringify(company);
console.log("TOKEN ====>>",token)
  return {
    ...config,
    headers: {
      authorization:  `Bearer eyJpdiI6IlMremptQXorNWlTZEJuV3djc1RkOXc9PSIsInZhbHVlIjoiZmY4OFp0NEhScFppMEdpeHNIam15aWxqdVQvVisxLzlUVGovM0tXTk8xcmFYMEpzWDhjWDArNUtJWld4QjJyUndLSkRpMnd2WHgzdUJGWGxtVWZKS2VsbUF3OUM2dGNMNGk3SjFxdmJUQjRUQzU1dG1pZ0pFNndXSkZ4SzZXSFMiLCJtYWMiOiI2ZDk2YzBlZjI5MDAxN2YzNTRjMTljNzgzNjQ4ZTZlNjgxMTljODc5MTNlZjQ5YWNjMTIxZjJlNjg4MjllNmVjIiwidGFnIjoiIn0` ,
    },
  };
});

const responseBody = (response) => response.data;

const requests = {
  get: (url, body, headers) =>
    instance.get(url, body, headers).then(responseBody),

  post: (url, body) => instance.post(url, body).then(responseBody),

  put: (url, body, headers) =>
    instance.put(url, body, headers).then(responseBody),

  patch: (url, body) => instance.patch(url, body).then(responseBody),

  delete: (url, body) => instance.delete(url, body).then(responseBody),
};

export default requests;
