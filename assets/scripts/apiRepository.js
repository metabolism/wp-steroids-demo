import Vue from "vue";
export default {

    getData(){

        return new Promise((resolve, reject) => {

            Vue.http.get(window.blog.home_url + '/app/uploads/data.json').then(response => {

                resolve(response.body);

            },reject)
        })
    },
}